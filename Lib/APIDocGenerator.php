<?php
/**
 * This file is part of DocumentacionAPI plugin for FacturaScripts
 * Copyright (C) 2024 BEPLY TECHNOLOGIES SL
 * Desarrollador: Tono Mollá González <tono@beply.es>
 *
 * This program is licensed and may be used, modified and redistributed under the terms
 * of the European Public License (EUPL), either version 1.1 or (at your option)
 * any later version as soon as they are approved by the European Commission.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace FacturaScripts\Plugins\DocumentacionAPI\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Lib\API\APIModel;
use FacturaScripts\Core\Request;
use FacturaScripts\Core\Response;
use SimpleXMLElement;

class APIDocGenerator
{
    private $openapi;
    private $modelCache = [];
    private $request;
    private $response;

    public function __construct()
    {
        $this->openapi = [
            'paths' => [],
            'components' => [
                'schemas' => []
            ]
        ];
        
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
    }

    public function generate(): array
    {
        $this->openapi = [
            'paths' => [],
            'components' => [
                'schemas' => []
            ],
            'tags' => $this->generateTags()
        ];
        
        $this->loadResources();
        return $this->openapi;
    }

    private function generateTags(): array
    {
        return [
            [
                'name' => 'Modelos',
                'description' => 'Recursos principales del sistema'
            ],
            [
                'name' => 'Custom',
                'description' => 'Recursos personalizados'
            ]
        ];
    }

    private function getTagForModel(string $modelName): string
    {
        return 'Modelos';
    }

    private function loadResources(): void
    {
        // Obtenemos los recursos usando APIModel
        $apiModel = new \FacturaScripts\Core\Lib\API\APIModel($this->response, $this->request, []);
        $resources = $apiModel->getResources();

        // Procesamos cada recurso
        foreach ($resources as $resourceName => $resourceData) {
            $modelName = $resourceData['Name'];
            if (!$this->isSystemModel($modelName)) {
                $this->addResourceToOpenAPI($modelName);
            }
        }

        // Añadimos los recursos personalizados
        foreach (ApiRoot::getCustomResources() as $customResource) {
            // Los recursos personalizados se añaden como endpoints especiales
            $this->addCustomResourceToOpenAPI($customResource);
        }
    }

    private function addCustomResourceToOpenAPI(string $resource): void
    {
        $basePath = '/api/3/' . $resource;
        
        // Los recursos personalizados solo tienen POST por defecto
        $this->openapi['paths'][$basePath] = [];

        // Añadimos ejemplos específicos según el tipo de recurso
        switch ($resource) {
            case 'crearFacturaCliente':
                $this->openapi['paths'][$basePath]['post'] = [
                    'tags' => ['Custom'],
                    'summary' => 'Crear factura de cliente',
                    'description' => 'Crea una nueva factura de cliente con sus líneas',
                    'parameters' => [
                        [
                            'name' => 'codcliente',
                            'in' => 'formData',
                            'description' => 'Código del cliente',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'codalmacen',
                            'in' => 'formData',
                            'description' => 'Código del almacén (opcional)',
                            'required' => false,
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'fecha',
                            'in' => 'formData',
                            'description' => 'Fecha de la factura (opcional)',
                            'required' => false,
                            'schema' => ['type' => 'string', 'format' => 'date']
                        ],
                        [
                            'name' => 'hora',
                            'in' => 'formData',
                            'description' => 'Hora de la factura (opcional)',
                            'required' => false,
                            'schema' => ['type' => 'string', 'format' => 'time']
                        ],
                        [
                            'name' => 'coddivisa',
                            'in' => 'formData',
                            'description' => 'Código de la divisa (opcional)',
                            'required' => false,
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'lineas',
                            'in' => 'formData',
                            'description' => 'Array JSON de líneas de la factura',
                            'required' => true,
                            'schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'referencia' => ['type' => 'string', 'description' => 'Referencia del producto'],
                                        'descripcion' => ['type' => 'string', 'description' => 'Descripción de la línea'],
                                        'cantidad' => ['type' => 'number', 'description' => 'Cantidad'],
                                        'pvpunitario' => ['type' => 'number', 'description' => 'Precio unitario'],
                                        'dtopor' => ['type' => 'number', 'description' => 'Porcentaje de descuento'],
                                        'codimpuesto' => ['type' => 'string', 'description' => 'Código del impuesto'],
                                        'suplido' => ['type' => 'boolean', 'description' => 'Es un suplido']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'name' => 'pagada',
                            'in' => 'formData',
                            'description' => 'Marcar la factura como pagada',
                            'required' => false,
                            'schema' => ['type' => 'boolean']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Factura creada correctamente',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'doc' => ['type' => 'object', 'description' => 'Datos de la factura'],
                                            'lines' => ['type' => 'array', 'description' => 'Líneas de la factura']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Error en los datos enviados',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'No autorizado - Token inválido o expirado',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string', 'example' => 'Token inválido o expirado']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            case 'exportarFacturaCliente':
                $this->openapi['paths'][$basePath]['get'] = [
                    'tags' => ['Custom'],
                    'summary' => 'Exportar factura de cliente',
                    'description' => 'Exporta una factura de cliente en el formato especificado',
                    'parameters' => [
                        [
                            'name' => 'code',
                            'in' => 'path',
                            'description' => 'ID de la factura',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'type',
                            'in' => 'query',
                            'description' => 'Tipo de exportación (PDF por defecto)',
                            'required' => false,
                            'schema' => ['type' => 'string', 'enum' => ['PDF']]
                        ],
                        [
                            'name' => 'format',
                            'in' => 'query',
                            'description' => 'Formato del documento (0 por defecto)',
                            'required' => false,
                            'schema' => ['type' => 'integer']
                        ],
                        [
                            'name' => 'lang',
                            'in' => 'query',
                            'description' => 'Código de idioma',
                            'required' => false,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Documento exportado',
                            'content' => [
                                'application/pdf' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ]
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Error en los datos enviados',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'No autorizado - Token inválido o expirado',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string', 'example' => 'Token inválido o expirado']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'Factura no encontrada',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string', 'example' => 'Invoice not found']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            default:
                // Para recursos personalizados genéricos
                $this->openapi['paths'][$basePath]['post'] = [
                    'tags' => ['Custom'],
                    'summary' => 'Recurso personalizado: ' . $resource,
                    'description' => 'Endpoint personalizado de la API',
                    'parameters' => [
                        [
                            'name' => 'token',
                            'in' => 'header',
                            'description' => 'Token de autenticación',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ],
                        [
                            'name' => 'data',
                            'in' => 'formData',
                            'description' => 'Datos en formato form-data',
                            'required' => true,
                            'schema' => ['type' => 'object']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Operación completada',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'ok'],
                                            'data' => ['type' => 'object', 'description' => 'Datos de respuesta específicos del endpoint']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Error en la petición',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string', 'example' => 'Parámetros inválidos o faltantes']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '401' => [
                            'description' => 'No autorizado - Token inválido o expirado',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'error'],
                                            'message' => ['type' => 'string', 'example' => 'Token inválido o expirado']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;
        }
    }

    private function isSystemModel(string $modelName): bool
    {
        // Lista de modelos que no queremos documentar
        $systemModels = [
            'CodeModel',
            'Base',
            'PageOption',
            'Settings',
            'User',
            'Role'
            // Añade aquí otros modelos que no quieras documentar
        ];

        foreach ($systemModels as $systemModel) {
            if (strpos($modelName, $systemModel) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getModelProperties(string $modelName): array
    {
        if (isset($this->modelCache[$modelName])) {
            return $this->modelCache[$modelName];
        }

        $properties = [];
        
        // Cargamos la estructura de la tabla
        $tablePath = Tools::folder('Dinamic', 'Table') . '/' . strtolower($modelName) . '.xml';
        if (file_exists($tablePath)) {
            $xml = simplexml_load_file($tablePath);
            if ($xml) {
                foreach ($xml->column as $column) {
                    $properties[(string)$column['name']] = [
                        'type' => $this->getPropertyTypeFromDB((string)$column['type']),
                        'description' => $this->getColumnDescription($column),
                        'required' => ((string)$column['null'] === 'NO'),
                        'example' => $this->getExampleValue((string)$column['type'], (string)$column['name'])
                    ];

                    if (isset($column['default'])) {
                        $properties[(string)$column['name']]['default'] = (string)$column['default'];
                    }

                    // Añadimos información de longitud si existe
                    if (isset($column['maxlength'])) {
                        $properties[(string)$column['name']]['maxLength'] = (int)$column['maxlength'];
                    }
                }
            }
        }

        // Cargamos también las propiedades del modelo
        $modelClass = "\\FacturaScripts\\Dinamic\\Model\\" . $modelName;
        if (class_exists($modelClass)) {
            $model = new $modelClass();
            
            // Verificamos si el modelo tiene el método getModelFields
            if (method_exists($model, 'getModelFields')) {
                // Añadimos información adicional del modelo
                foreach ($model->getModelFields() as $field => $info) {
                    if (!isset($properties[$field])) {
                        $properties[$field] = [
                            'type' => $this->getPropertyTypeFromDB($info['type']),
                            'description' => $this->getFieldDescription($field, $info),
                            'required' => !$info['is_nullable'],
                            'example' => $this->getExampleValue($info['type'], $field)
                        ];

                        // Añadimos información de longitud si existe
                        if (isset($info['maxlength'])) {
                            $properties[$field]['maxLength'] = $info['maxlength'];
                        }
                    }

                    // Añadimos información de clave primaria
                    if ($field === $model->primaryColumn()) {
                        $properties[$field]['description'] .= ' (Primary Key)';
                    }
                }
            } else {
                // Si el modelo no tiene getModelFields, intentamos obtener las propiedades básicas
                if (method_exists($model, 'primaryColumn')) {
                    $primaryKey = $model->primaryColumn();
                    if (!isset($properties[$primaryKey])) {
                        $properties[$primaryKey] = [
                            'type' => 'string',
                            'description' => $primaryKey . ' (Primary Key)',
                            'required' => true,
                            'example' => '1'
                        ];
                    }
                }
            }
        }

        $this->modelCache[$modelName] = $properties;
        return $properties;
    }

    private function getExampleValue(string $type, string $fieldName): mixed
    {
        // Valores de ejemplo basados en el nombre del campo
        if (str_contains($fieldName, 'nombre') || str_contains($fieldName, 'name')) {
            return 'Ejemplo Nombre';
        }
        if (str_contains($fieldName, 'descripcion') || str_contains($fieldName, 'description')) {
            return 'Descripción de ejemplo';
        }
        if (str_contains($fieldName, 'email')) {
            return 'ejemplo@email.com';
        }
        if (str_contains($fieldName, 'telefono') || str_contains($fieldName, 'phone')) {
            return '+34 666 777 888';
        }
        if (str_contains($fieldName, 'web') || str_contains($fieldName, 'url')) {
            return 'https://ejemplo.com';
        }

        // Valores de ejemplo basados en el tipo
        if (strpos($type, 'int') !== false) {
            return 1;
        }
        if (strpos($type, 'decimal') !== false || strpos($type, 'double') !== false) {
            return 123.45;
        }
        if (strpos($type, 'bool') !== false) {
            return true;
        }
        if (strpos($type, 'date') !== false) {
            return date('Y-m-d');
        }
        if (strpos($type, 'time') !== false) {
            return date('Y-m-d H:i:s');
        }

        // Valor por defecto para strings
        return 'Ejemplo';
    }

    private function getPropertyTypeFromDB(string $type): string
    {
        if (strpos($type, 'int') !== false) {
            return 'integer';
        }
        if (strpos($type, 'decimal') !== false || strpos($type, 'double') !== false) {
            return 'number';
        }
        if (strpos($type, 'bool') !== false) {
            return 'boolean';
        }
        if (strpos($type, 'date') !== false) {
            return 'string';
            // Podríamos añadir format: date-time
        }
        return 'string';
    }

    private function addResourceToOpenAPI(string $resource): void
    {
        // Convertimos el nombre del recurso a su versión en la API
        $apiPath = $this->getApiPath($resource);
        $basePath = '/api/3/' . $apiPath;
        
        $properties = $this->getModelProperties($resource);
        
        // Añadimos el schema del modelo con propiedades requeridas
        $requiredProperties = [];
        foreach ($properties as $name => $property) {
            if ($property['required'] ?? false) {
                $requiredProperties[] = $name;
            }
        }
        
        $this->openapi['components']['schemas'][$resource] = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $requiredProperties
        ];

        // Creamos un ejemplo completo del modelo
        $example = [];
        foreach ($properties as $name => $property) {
            $example[$name] = $property['example'] ?? null;
        }

        // Asignamos el tag correspondiente
        $tag = $this->getTagForModel($resource);

        // Respuesta de error de autorización común para todos los endpoints
        $unauthorizedResponse = [
            'description' => 'No autorizado - Token inválido o expirado',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string', 'example' => 'error'],
                            'message' => ['type' => 'string', 'example' => 'Token inválido o expirado']
                        ]
                    ]
                ]
            ]
        ];

        // GET - Lista
        $this->openapi['paths'][$basePath]['get'] = [
            'tags' => [$tag],
            'summary' => 'Obtener lista de ' . $resource,
            'parameters' => [
                [
                    'name' => 'offset',
                    'in' => 'query',
                    'description' => 'Número de registros a saltar',
                    'schema' => ['type' => 'integer', 'default' => 0]
                ],
                [
                    'name' => 'limit',
                    'in' => 'query',
                    'description' => 'Número máximo de registros',
                    'schema' => ['type' => 'integer', 'default' => 50]
                ],
                [
                    'name' => 'filter',
                    'in' => 'query',
                    'description' => 'Filtros (campo:valor)',
                    'schema' => ['type' => 'string']
                ],
                [
                    'name' => 'order',
                    'in' => 'query',
                    'description' => 'Orden (campo:asc|desc)',
                    'schema' => ['type' => 'string']
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Lista de ' . $resource,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/' . $resource]
                            ],
                            'example' => [$example]
                        ]
                    ]
                ],
                '401' => $unauthorizedResponse
            ]
        ];

        // POST - Crear
        $this->openapi['paths'][$basePath]['post'] = [
            'tags' => [$tag],
            'summary' => 'Crear nuevo ' . $resource,
            'description' => 'Crea un nuevo ' . $resource,
            'parameters' => $this->generateParameters($properties),
            'responses' => [
                '201' => [
                    'description' => $resource . ' creado correctamente',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/' . $resource],
                            'example' => $example
                        ]
                    ]
                ],
                '400' => [
                    'description' => 'Error de validación',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'string', 'example' => 'error'],
                                    'message' => ['type' => 'string', 'example' => 'Campo requerido no proporcionado o inválido']
                                ]
                            ]
                        ]
                    ]
                ],
                '401' => $unauthorizedResponse
            ]
        ];

        // Operaciones individuales
        $this->openapi['paths'][$basePath . '/{id}'] = [
            'get' => [
                'tags' => [$tag],
                'summary' => 'Obtener ' . $resource . ' por ID',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => $resource . ' encontrado',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/' . $resource],
                                'example' => $example
                            ]
                        ]
                    ],
                    '404' => [
                        'description' => $resource . ' no encontrado'
                    ],
                    '401' => $unauthorizedResponse
                ]
            ],
            'put' => [
                'tags' => [$tag],
                'summary' => 'Actualizar ' . $resource,
                'description' => 'Actualiza un ' . $resource . ' existente',
                'parameters' => array_merge(
                    [[
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string']
                    ]],
                    $this->generateParameters($properties)
                ),
                'responses' => [
                    '200' => [
                        'description' => $resource . ' actualizado',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/' . $resource],
                                'example' => $example
                            ]
                        ]
                    ],
                    '404' => [
                        'description' => $resource . ' no encontrado'
                    ],
                    '401' => $unauthorizedResponse
                ]
            ],
            'delete' => [
                'tags' => [$tag],
                'summary' => 'Eliminar ' . $resource,
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => $resource . ' eliminado'
                    ],
                    '404' => [
                        'description' => $resource . ' no encontrado'
                    ],
                    '401' => $unauthorizedResponse
                ]
            ]
        ];
    }

    private function generateParameters(array $properties): array
    {
        $parameters = [];
        foreach ($properties as $name => $property) {
            $parameters[] = [
                'name' => $name,
                'in' => 'formData',
                'description' => $property['description'],
                'required' => $property['required'] ?? false,
                'schema' => [
                    'type' => $property['type']
                ]
            ];
            
            // Añadimos el ejemplo si existe
            if (isset($property['example'])) {
                $parameters[count($parameters) - 1]['example'] = $property['example'];
            }
            
            // Añadimos la longitud máxima si existe
            if (isset($property['maxLength'])) {
                $parameters[count($parameters) - 1]['schema']['maxLength'] = $property['maxLength'];
            }
        }
        return $parameters;
    }

    private function getApiPath(string $modelName): string
    {
        // Usamos el mismo sistema de pluralización que usa la API del Core
        $apiModel = new \FacturaScripts\Core\Lib\API\APIModel($this->response, $this->request, []);
        $resources = $apiModel->getResources();
        
        // Buscamos el recurso que corresponde a este modelo
        foreach ($resources as $resourceName => $resourceData) {
            if ($resourceData['Name'] === $modelName) {
                return $resourceName;
            }
        }

        // Si no se encuentra, convertimos el nombre del modelo a minúsculas y plural
        return strtolower($modelName) . 's';
    }

    private function getColumnDescription(SimpleXMLElement $column): string
    {
        $description = (string)$column['title'] ?: (string)$column['name'];
        
        // Añadimos información adicional a la descripción
        $details = [];
        
        if ((string)$column['null'] === 'NO') {
            $details[] = 'Requerido';
        }
        
        if (isset($column['maxlength'])) {
            $details[] = 'Máximo ' . $column['maxlength'] . ' caracteres';
        }
        
        if (isset($column['default'])) {
            $details[] = 'Valor por defecto: ' . $column['default'];
        }
        
        if (!empty($details)) {
            $description .= ' (' . implode(', ', $details) . ')';
        }
        
        return $description;
    }

    private function getFieldDescription(string $field, array $info): string
    {
        $description = $field;
        
        // Añadimos información adicional a la descripción
        $details = [];
        
        if (!$info['is_nullable']) {
            $details[] = 'Requerido';
        }
        
        if (isset($info['maxlength'])) {
            $details[] = 'Máximo ' . $info['maxlength'] . ' caracteres';
        }
        
        if (isset($info['default'])) {
            $details[] = 'Valor por defecto: ' . $info['default'];
        }
        
        if (!empty($details)) {
            $description .= ' (' . implode(', ', $details) . ')';
        }
        
        return $description;
    }
} 
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

namespace FacturaScripts\Plugins\DocumentacionAPI;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Plugins\DocumentacionAPI\Lib\APIDocGenerator;

class Init extends InitClass
{
    public function init(): void
    {
        // Registramos la ruta en /swagger
        Kernel::addRoute('/swagger', 'SwaggerDocs', 0);
        $this->generateSwaggerJson();
    }

    public function update(): void
    {
        $this->generateSwaggerJson();
    }

    public function uninstall(): void
    {
        // Eliminamos el archivo JSON al desinstalar
        $jsonFile = FS_FOLDER . '/MyFiles/swagger/openapi.json';
        if (file_exists($jsonFile)) {
            unlink($jsonFile);
        }
    }

    private function generateSwaggerJson(): void
    {
        // Creamos el directorio si no existe
        $dir = FS_FOLDER . '/MyFiles/swagger';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Generamos el JSON
        $generator = new APIDocGenerator();
        $openapi = $generator->generate();

        // Estructura base de OpenAPI
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'FacturaScripts API',
                'version' => '1.0.0',
                'description' => 'Todas las rutas empiezan por /api/3. Requiere token en cabecera. Los POST y PUT usan form-data, no JSON. Filtros disponibles en GET: filter=campo:valor, order=campo:desc, offset=0&limit=50'
            ],
            'servers' => [
                [
                    'url' => '{schema}://{host}/api/3',
                    'description' => 'API Server',
                    'variables' => [
                        'schema' => [
                            'enum' => ['http', 'https'],
                            'default' => 'http'
                        ],
                        'host' => [
                            'default' => 'localhost'
                        ]
                    ]
                ]
            ],
            'paths' => $openapi['paths'] ?? [],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'token'
                    ]
                ]
            ],
            'security' => [
                ['ApiKeyAuth' => []]
            ]
        ];

        // Guardamos el JSON
        $jsonFile = $dir . '/openapi.json';
        file_put_contents($jsonFile, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
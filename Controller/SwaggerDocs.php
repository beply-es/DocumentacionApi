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

namespace FacturaScripts\Plugins\DocumentacionAPI\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;

class SwaggerDocs extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Swagger';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        // Si es una petición para obtener el JSON de la API
        if ($this->request->query->get('action') === 'get-json') {
            $this->sendJSON();
            die();
        }

        // Si no, mostramos la UI de Swagger
        $this->setTemplate('SwaggerDocs');
        $this->generateSwaggerUI();
    }

    protected function generateSwaggerUI(): void
    {
        $this->title = 'Swagger Documentation';
        $this->content = '<div class="search-container">
                <input type="text" id="api-search" placeholder="Filtrar el schema por cualquier texto (URL, descripción, método...)" />
            </div>
            <div id="swagger-ui"></div>
            <link rel="stylesheet" href="' . FS_ROUTE . '/Plugins/DocumentacionAPI/Assets/css/swagger-ui.min.css">
            <script src="' . FS_ROUTE . '/Plugins/DocumentacionAPI/Assets/js/swagger-ui-bundle.min.js"></script>
            <style>
                body { margin: 0; padding: 0px; }
                .swagger-ui .topbar { display: none }
                #swagger-ui { margin-top: 20px; }
                .search-container {
                    margin: 20px 0;
                    padding: 0 20px;
                }
                #api-search {
                    width: 100%;
                    padding: 10px;
                    font-size: 16px;
                    border: 2px solid #41444e;
                    border-radius: 4px;
                    box-sizing: border-box;
                }
                #api-search:focus {
                    outline: none;
                    border-color: #49cc90;
                }
            </style>
            <script>
                let swaggerUI;
                window.onload = function() {
                    const baseUrl = "' . $this->url() . '";
                    
                    function initSwaggerUI(filterValue = "") {
                        const url = `swagger?action=get-json${filterValue ? `&filter=${filterValue}` : ""}`;
                        
                        if (swaggerUI) {
                            swaggerUI.specActions.updateUrl(url);
                            swaggerUI.specActions.download();
                        } else {
                            swaggerUI = SwaggerUIBundle({
                                url: url,
                                dom_id: "#swagger-ui",
                                deepLinking: true,
                                presets: [
                                    SwaggerUIBundle.presets.apis,
                                    SwaggerUIBundle.SwaggerUIStandalonePreset
                                ],
                                plugins: [
                                    SwaggerUIBundle.plugins.DownloadUrl
                                ],
                                layout: "BaseLayout",
                                docExpansion: "none",
                                filter: false
                            });
                        }
                    }

                    // Inicializamos sin filtro
                    initSwaggerUI();

                    // Configuramos el buscador
                    const searchInput = document.getElementById("api-search");
                    if (searchInput) {
                        let timeoutId;
                        searchInput.oninput = function(e) {
                            clearTimeout(timeoutId);
                            timeoutId = setTimeout(() => {
                                const searchText = e.target.value.trim();
                                initSwaggerUI(searchText);
                            }, 300);
                        };
                    }
                };
            </script>';
    }

    protected function sendJSON(): void
    {
        $jsonFile = FS_FOLDER . '/MyFiles/swagger/openapi.json';
        
        if (!file_exists($jsonFile)) {
            // Si el archivo no existe, devolvemos un error
            header('HTTP/1.0 404 Not Found');
            echo json_encode(['error' => 'Swagger configuration not found']);
            return;
        }

        // Leemos el JSON
        $json = file_get_contents($jsonFile);
        
        // Decodificamos para poder modificar la URL del servidor
        $spec = json_decode($json, true);

        // Nos aseguramos de que las estructuras básicas existan
        if (!isset($spec['paths'])) {
            $spec['paths'] = [];
        }
        if (!isset($spec['components'])) {
            $spec['components'] = [];
        }
        if (!isset($spec['components']['schemas'])) {
            $spec['components']['schemas'] = [];
        }
        
        // Generamos la documentación dinámica
        $generator = new \FacturaScripts\Plugins\DocumentacionAPI\Lib\APIDocGenerator();
        $newSpec = $generator->generate();
        
        // Fusionamos con el spec existente
        $spec['paths'] = array_merge($spec['paths'], $newSpec['paths'] ?? []);
        $spec['components']['schemas'] = array_merge($spec['components']['schemas'], $newSpec['components']['schemas'] ?? []);
        $spec['tags'] = $newSpec['tags'] ?? [];
        
        // Actualizamos la URL del servidor con la actual
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            ? 'https' : 'http';
        $spec['servers'] = [[
            'url' => rtrim($scheme . '://' . $this->request->host() . FS_ROUTE, '/'),
            'description' => 'API Server'
        ]];

        // Aplicamos filtro si existe
        $filter = $this->request->query->get('filter');
        if (!empty($filter)) {
            $filteredPaths = [];
            $filteredSchemas = [];
            
            // Filtramos los paths
            foreach ($spec['paths'] as $path => $pathData) {
                $matchFound = false;
                
                // Buscamos en la URL
                if (stripos($path, $filter) !== false) {
                    $matchFound = true;
                }
                
                // Buscamos en las descripciones y tags de cada método
                foreach ($pathData as $method => $methodData) {
                    if (
                        stripos($methodData['summary'] ?? '', $filter) !== false ||
                        stripos($methodData['description'] ?? '', $filter) !== false ||
                        !empty(array_filter($methodData['tags'] ?? [], function($tag) use ($filter) {
                            return stripos($tag, $filter) !== false;
                        }))
                    ) {
                        $matchFound = true;
                        break;
                    }
                }
                
                if ($matchFound) {
                    $filteredPaths[$path] = $pathData;
                    
                    // Guardamos también los schemas relacionados
                    foreach ($pathData as $method => $methodData) {
                        if (isset($methodData['requestBody']['content']['application/json']['schema']['$ref'])) {
                            $schemaRef = basename($methodData['requestBody']['content']['application/json']['schema']['$ref']);
                            if (isset($spec['components']['schemas'][$schemaRef])) {
                                $filteredSchemas[$schemaRef] = $spec['components']['schemas'][$schemaRef];
                            }
                        }
                        if (isset($methodData['responses']['200']['content']['application/json']['schema']['$ref'])) {
                            $schemaRef = basename($methodData['responses']['200']['content']['application/json']['schema']['$ref']);
                            if (isset($spec['components']['schemas'][$schemaRef])) {
                                $filteredSchemas[$schemaRef] = $spec['components']['schemas'][$schemaRef];
                            }
                        }
                    }
                }
            }
            
            if (!empty($filteredPaths)) {
                $spec['paths'] = $filteredPaths;
                $spec['components']['schemas'] = $filteredSchemas;
            }
        }
        
        // Enviamos las cabeceras CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: X-Requested-With');
        header('Content-Type: application/json');
        
        // Enviamos el JSON
        echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} 
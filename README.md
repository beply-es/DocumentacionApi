# DocumentacionAPI

Plugin de FacturaScripts que genera y muestra documentación interactiva de la API mediante Swagger UI.

## Compatibilidad

- FacturaScripts 2026.1 o posterior.
- Versión verificada: FacturaScripts 2026.6 con PHP 8.2.

## Instalación y uso

1. Instala `DocumentacionAPI-1.2.zip` desde el administrador de plugins de FacturaScripts.
2. Activa el plugin.
3. Accede con un usuario autorizado a `/swagger`.

La especificación usa OpenAPI 3.0, documenta los recursos disponibles en `/api/3` y configura la autenticación mediante la cabecera `token`. La pantalla incluye un filtro de texto para localizar rutas, métodos, descripciones y etiquetas.

## Desarrollo y pruebas

Las pruebas de contrato están en `Test/`. Las pruebas de integración y navegador están en `tests/` e incluyen:

- activación y ejecución sobre un core real de FacturaScripts;
- generación del documento persistido y del documento servido;
- validación formal de OpenAPI 3.0;
- carga de Swagger UI y sus assets locales;
- filtrado de rutas y comprobación de errores de navegador.

## Licencia

EUPL 1.1 o posterior. Consulta [LICENSE](LICENSE).

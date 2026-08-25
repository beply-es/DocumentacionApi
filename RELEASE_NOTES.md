# DocumentacionAPI 1.2 lleva Swagger a FacturaScripts 2026

Beply publica DocumentacionAPI 1.2, una actualización centrada en recuperar la compatibilidad con la familia FacturaScripts 2026 y mejorar la calidad de la especificación generada.

La nueva versión adapta el plugin a las APIs actuales de inicialización, peticiones y respuestas de FacturaScripts, corrige las rutas de los recursos de Swagger UI y elimina avisos de compatibilidad con PHP 8.2. Además, el generador deja atrás construcciones heredadas de Swagger 2 y entrega un documento OpenAPI 3.0 válido, con cuerpos de formulario, campos obligatorios, esquemas y URL del servidor correctamente definidos.

La versión se ha verificado en un workspace limpio de FacturaScripts 2026.6. En ese entorno, DocumentacionAPI se instala y activa correctamente, publica 183 rutas y 77 esquemas, carga la interfaz Swagger desde recursos locales y mantiene operativo el filtro de documentación.

Esta actualización también reconoce la colaboración de Miguel (`tcrmmartin_22487`), cuya propuesta sirvió de punto de partida para restaurar la compatibilidad con FacturaScripts 2026.

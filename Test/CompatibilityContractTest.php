<?php

declare(strict_types=1);

namespace FacturaScripts\Test\Plugins;

use PHPUnit\Framework\TestCase;

final class CompatibilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function testManifestDeclaresReviewedCompatibilityRelease(): void
    {
        $manifest = $this->read('facturascripts.ini');

        self::assertMatchesRegularExpression('/^name\s*=\s*[\'\"]DocumentacionAPI[\'\"]$/m', $manifest);
        self::assertMatchesRegularExpression('/^version\s*=\s*1\.2$/m', $manifest);
        self::assertMatchesRegularExpression('/^min_version\s*=\s*2026\.1$/m', $manifest);
    }

    public function testRuntimeCodeTargetsTheCurrentCoreApi(): void
    {
        $init = $this->read('Init.php');
        $controller = $this->read('Controller/SwaggerDocs.php');
        $generator = $this->read('Lib/APIDocGenerator.php');

        self::assertStringContainsString('FacturaScripts\\Core\\Template\\InitClass', $init);
        self::assertStringContainsString('/Plugins/DocumentacionAPI/Assets/css/swagger-ui.min.css', $controller);
        self::assertStringContainsString('/Plugins/DocumentacionAPI/Assets/js/swagger-ui-bundle.min.js', $controller);
        self::assertStringContainsString('FacturaScripts\\Plugins\\DocumentacionAPI\\Lib\\APIDocGenerator', $controller);
        self::assertMatchesRegularExpression('/public\\s+string\\s+\\$content\\s*=\\s*[\'\"]{2}/', $controller);
        self::assertDoesNotMatchRegularExpression('/request->query->get\(/', $controller);
        self::assertStringContainsString('$this->request()->query(', $controller);
        self::assertStringContainsString('FacturaScripts\\Core\\Request', $generator);
        self::assertStringContainsString('FacturaScripts\\Core\\Response', $generator);
        self::assertStringNotContainsString('Symfony\\Component\\HttpFoundation', $generator);
    }

    public function testSwaggerAssetsAreBundledLocally(): void
    {
        self::assertFileExists($this->root . '/Assets/css/swagger-ui.min.css');
        self::assertFileExists($this->root . '/Assets/js/swagger-ui-bundle.min.js');
    }

    private function read(string $path): string
    {
        $fullPath = $this->root . '/' . $path;
        self::assertFileExists($fullPath);

        return (string)file_get_contents($fullPath);
    }
}

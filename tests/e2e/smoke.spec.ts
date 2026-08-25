import { expect, type Page, test } from '@playwright/test';
import SwaggerParser from '@apidevtools/swagger-parser';

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8062';
const LOGIN_USER = process.env.E2E_USER || 'admin';
const LOGIN_PASSWORD = process.env.E2E_PASSWORD;
const RUNTIME_ERROR_PATTERN = /Fatal error|Deprecated:|Warning:|Error procesando el XML/i;

async function login(page: Page): Promise<void> {
    if (!LOGIN_PASSWORD) {
        throw new Error('E2E_PASSWORD is required');
    }

    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="fsNick"]', LOGIN_USER);
    await page.fill('input[name="fsPassword"]', LOGIN_PASSWORD);
    await page.locator('form[action$="login"] button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');
    await expect(page).not.toHaveURL(/\/login$/);
}

test('DocumentacionAPI publishes a functional Swagger UI', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('console', message => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    await login(page);

    await page.goto(`${BASE_URL}/AdminPlugins`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText(RUNTIME_ERROR_PATTERN);
    await expect(page.locator('body')).toContainText('DocumentacionAPI');

    await page.goto(`${BASE_URL}/swagger`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).not.toContainText(RUNTIME_ERROR_PATTERN);
    await expect(page.locator('#api-search')).toBeVisible();
    await expect(page.locator('#swagger-ui')).toBeVisible();
    await expect(page.locator('.swagger-ui')).toBeVisible();

    const jsonResponse = await page.request.get(`${BASE_URL}/swagger?action=get-json`);
    expect(jsonResponse.ok()).toBeTruthy();
    const spec = await jsonResponse.json();
    expect(spec.openapi).toBe('3.0.0');
    expect(Object.keys(spec.paths ?? {}).length).toBeGreaterThan(100);
    expect(Object.keys(spec.components?.schemas ?? {}).length).toBeGreaterThan(50);
    expect(spec.servers?.[0]?.url).toBe(BASE_URL);
    await SwaggerParser.validate(spec);

    const cssResponse = await page.request.get(`${BASE_URL}/Plugins/DocumentacionAPI/Assets/css/swagger-ui.min.css`);
    const jsResponse = await page.request.get(`${BASE_URL}/Plugins/DocumentacionAPI/Assets/js/swagger-ui-bundle.min.js`);
    expect(cssResponse.ok()).toBeTruthy();
    expect(jsResponse.ok()).toBeTruthy();

    const filteredResponsePromise = page.waitForResponse(response => response.url().includes('filter=cliente'));
    await page.locator('#api-search').fill('cliente');
    const filteredResponse = await filteredResponsePromise;
    expect(filteredResponse.ok()).toBeTruthy();
    const filteredSpec = await filteredResponse.json();
    expect(Object.keys(filteredSpec.paths ?? {}).length).toBeGreaterThan(0);
    expect(Object.keys(filteredSpec.paths ?? {}).length).toBeLessThan(Object.keys(spec.paths).length);

    expect(consoleErrors).toEqual([]);
});

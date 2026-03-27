import http from 'k6/http';
import { Rate } from 'k6/metrics';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.BASE_URL || '').replace(/\/+$/, '');
const apiToken = __ENV.API_TOKEN || '';

if (!baseUrl) {
    throw new Error('BASE_URL is required for the API smoke load test.');
}

const defaultHeaders = {
    Accept: 'application/json',
};

const authenticatedHeaders = apiToken
    ? {
        ...defaultHeaders,
        Authorization: `Bearer ${apiToken}`,
    }
    : defaultHeaders;

const healthSuccess = new Rate('health_success');
const projectsSuccess = new Rate('projects_success');
const inventoryStockBalancesSuccess = new Rate('inventory_stock_balances_success');
const salesOrdersSuccess = new Rate('sales_orders_success');
const webhookEndpointsSuccess = new Rate('webhook_endpoints_success');

export const options = {
    vus: Number(__ENV.K6_VUS || 10),
    duration: __ENV.K6_DURATION || '60s',
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<1500'],
        health_success: ['rate>0.99'],
        projects_success: ['rate>0.95'],
        inventory_stock_balances_success: ['rate>0.95'],
        sales_orders_success: ['rate>0.95'],
        webhook_endpoints_success: ['rate>0.95'],
    },
};

function verify(response, endpoint, successRate, expectedStatus = 200) {
    const passed = check(
        response,
        {
            [`${endpoint} returned ${expectedStatus}`]: (res) => res.status === expectedStatus,
        },
        { endpoint },
    );

    successRate.add(passed);
}

export default function () {
    const healthResponse = http.get(`${baseUrl}/api/v1/health`, {
        headers: defaultHeaders,
        tags: { endpoint: 'health' },
    });
    verify(healthResponse, 'health', healthSuccess);

    if (apiToken) {
        const projectsResponse = http.get(`${baseUrl}/api/v1/projects?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'projects' },
        });
        verify(projectsResponse, 'projects', projectsSuccess);

        const stockBalancesResponse = http.get(`${baseUrl}/api/v1/inventory/stock-balances?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'inventory_stock_balances' },
        });
        verify(stockBalancesResponse, 'inventory_stock_balances', inventoryStockBalancesSuccess);

        const salesOrdersResponse = http.get(`${baseUrl}/api/v1/sales/orders?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'sales_orders' },
        });
        verify(salesOrdersResponse, 'sales_orders', salesOrdersSuccess);

        const webhookEndpointsResponse = http.get(`${baseUrl}/api/v1/webhooks/endpoints?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'webhook_endpoints' },
        });
        verify(webhookEndpointsResponse, 'webhook_endpoints', webhookEndpointsSuccess);
    }

    sleep(1);
}

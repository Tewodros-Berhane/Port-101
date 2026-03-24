import http from 'k6/http';
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

export const options = {
    vus: Number(__ENV.K6_VUS || 10),
    duration: __ENV.K6_DURATION || '60s',
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<1500'],
        'checks{endpoint:health}': ['rate>0.99'],
        'checks{endpoint:projects}': ['rate>0.95'],
        'checks{endpoint:inventory_stock_balances}': ['rate>0.95'],
        'checks{endpoint:sales_orders}': ['rate>0.95'],
        'checks{endpoint:webhook_endpoints}': ['rate>0.95'],
    },
};

function verify(response, endpoint, expectedStatus = 200) {
    check(
        response,
        {
            [`${endpoint} returned ${expectedStatus}`]: (res) => res.status === expectedStatus,
        },
        { endpoint },
    );
}

export default function () {
    const healthResponse = http.get(`${baseUrl}/api/v1/health`, {
        headers: defaultHeaders,
        tags: { endpoint: 'health' },
    });
    verify(healthResponse, 'health');

    if (apiToken) {
        const projectsResponse = http.get(`${baseUrl}/api/v1/projects?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'projects' },
        });
        verify(projectsResponse, 'projects');

        const stockBalancesResponse = http.get(`${baseUrl}/api/v1/inventory/stock-balances?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'inventory_stock_balances' },
        });
        verify(stockBalancesResponse, 'inventory_stock_balances');

        const salesOrdersResponse = http.get(`${baseUrl}/api/v1/sales/orders?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'sales_orders' },
        });
        verify(salesOrdersResponse, 'sales_orders');

        const webhookEndpointsResponse = http.get(`${baseUrl}/api/v1/webhooks/endpoints?per_page=25`, {
            headers: authenticatedHeaders,
            tags: { endpoint: 'webhook_endpoints' },
        });
        verify(webhookEndpointsResponse, 'webhook_endpoints');
    }

    sleep(1);
}

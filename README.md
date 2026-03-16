# Restaurant Order & Delivery System

A backend service built with **Laravel 12** that integrates **Uber Eats** and **Uber Direct** for a restaurant system.

## Features

- Receive orders from Restaurant Website
- Receive orders from Uber Eats via Webhook
- Dispatch deliveries using Uber Direct
- Track order and delivery status in real-time
- Queue-based delivery dispatch with retry logic
- Webhook signature verification (HMAC SHA256)
- Test mode for local development without Uber credentials

---

## Tech Stack

- **PHP** 8.2+
- **Laravel** 12
- **PostgreSQL** 8+
- **Queue** — Database driver with Supervisor

---

## Requirements

- PHP 8.2+
- Composer
- PostgreSQL
- Laravel 12

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-username/restaurant-system.git
cd restaurant-system
```

### 2. Install dependencies

```bash
composer install
```

### 3. Copy environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure `.env`

```env
APP_NAME="Restaurant System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=restaurant_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

QUEUE_CONNECTION=database

# Uber Direct
UBER_DIRECT_CLIENT_ID=your_client_id
UBER_DIRECT_CLIENT_SECRET=your_client_secret
UBER_DIRECT_CUSTOMER_ID=your_customer_id
UBER_DIRECT_WEBHOOK_SECRET=your_webhook_secret

# Set true for local development — no real Uber credentials needed
UBER_DIRECT_TEST_MODE=true

RESTAURANT_PICKUP_ADDRESS="Your Restaurant, 123 Main St, Dhaka"
```

### 5. Create database

```bash
psql -U postgres -c "CREATE DATABASE restaurant_db WITH ENCODING 'UTF8';"
```

### 6. Run migrations

```bash
php artisan migrate
```

### 7. Start the server

```bash
php artisan serve
```

### 8. Start the queue worker

Open a new terminal and run:

```bash
php artisan queue:work --queue=deliveries --tries=3 --timeout=30
```

---

## Project Structure

```
app/
├── Contracts/
│   ├── DeliveryServiceInterface.php
│   ├── OrderRepositoryInterface.php
│   └── OrderServiceInterface.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── OrderController.php
│   │   └── Webhook/
│   │       ├── UberDirectController.php
│   │       └── UberEatsController.php
│   ├── Requests/
│   │   └── StoreOrderRequest.php
│   └── Resources/
│       ├── DeliveryResource.php
│       ├── OrderItemResource.php
│       └── OrderResource.php
├── Jobs/
│   └── DispatchUberDirectDelivery.php
├── Models/
│   ├── Delivery.php
│   ├── Order.php
│   └── OrderItem.php
├── Providers/
│   └── AppServiceProvider.php
├── Repositories/
│   └── OrderRepository.php
└── Services/
    ├── OrderService.php
    ├── UberAuthService.php
    └── UberDirectService.php
```

---

## Database Schema

### orders
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| external_order_id | string nullable | Uber Eats order ID |
| order_source | enum | `website` or `uber_eats` |
| customer_name | string | Customer full name |
| phone | string | Customer phone |
| address | text | Delivery address |
| status | enum | `pending`, `confirmed`, `preparing`, `ready_for_pickup`, `completed`, `cancelled` |
| created_at | timestamp | |
| updated_at | timestamp | |

### order_items
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| order_id | bigint | Foreign key → orders |
| item_name | string | Item name |
| quantity | integer | Quantity |
| price | decimal | Price |
| created_at | timestamp | |
| updated_at | timestamp | |

### deliveries
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| order_id | bigint | Foreign key → orders |
| provider | string | Default: `uber_direct` |
| external_delivery_id | string nullable | Uber Direct delivery ID |
| delivery_status | enum | `pending`, `courier_assigned`, `courier_picked_up`, `delivered`, `cancelled` |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## API Documentation

### Base URL
```
http://127.0.0.1:8000/api
```

---

### 1. Create Website Order

```
POST /api/orders
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "customer_name": "John Doe",
  "phone": "+8801711000000",
  "address": "123 Main St, Dhaka",
  "items": [
    { "name": "Burger", "qty": 1, "price": 5.00 },
    { "name": "Fries",  "qty": 2, "price": 2.50 }
  ]
}
```

**Response (201):**
```json
{
  "data": {
    "id": 1,
    "order_source": "website",
    "status": "pending",
    "customer_name": "John Doe",
    "phone": "+8801711000000",
    "address": "123 Main St, Dhaka",
    "items": [
      { "id": 1, "item_name": "Burger", "quantity": 1, "price": "5.00" },
      { "id": 2, "item_name": "Fries",  "quantity": 2, "price": "2.50" }
    ],
    "delivery": null,
    "created_at": "2026-03-16 10:00:00"
  }
}
```

---

### 2. Get All Orders (Dashboard)

```
GET /api/orders
```

**Headers:**
```
Accept: application/json
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "order_source": "website",
      "status": "ready_for_pickup",
      "customer_name": "John Doe",
      "items": [...],
      "delivery": {
        "provider": "uber_direct",
        "external_delivery_id": "FAKE-DEL-AB12CD34",
        "delivery_status": "courier_assigned",
        "created_at": "2026-03-16 10:05:00"
      },
      "created_at": "2026-03-16 10:00:00"
    }
  ]
}
```

---

### 3. Mark Order Ready & Dispatch Delivery

```
PATCH /api/orders/{id}/ready
```

**Headers:**
```
Accept: application/json
```

**Response (200):**
```json
{
  "message": "Order marked as ready. Delivery dispatched to queue."
}
```

> This triggers the Uber Direct delivery dispatch via queue job.

---

### 4. Uber Eats Order Webhook

```
POST /api/webhook/uber-eats/orders
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "order_id": "UE-TEST-001",
  "customer_name": "Alice Rahman",
  "address": "45 Broadway, Dhaka",
  "phone": "+8801811000000",
  "items": [
    { "name": "Pizza",       "qty": 1, "price": 12.00 },
    { "name": "Garlic Bread","qty": 2, "price": 3.50  }
  ]
}
```

**Response (201):**
```json
{
  "message": "Uber Eats order received successfully.",
  "data": {
    "id": 2,
    "external_order_id": "UE-TEST-001",
    "order_source": "uber_eats",
    "status": "confirmed",
    "items": [...],
    "delivery": null
  }
}
```

---

### 5. Uber Direct Delivery Status Webhook

```
POST /api/webhook/uber-direct/status
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
X-Uber-Signature: {HMAC_SHA256_signature}  (optional in local)
```

**Request Body:**
```json
{
  "delivery_id": "FAKE-DEL-AB12CD34",
  "status": "courier_assigned"
}
```

**Available Statuses:**
- `courier_assigned`
- `courier_picked_up`
- `delivered`
- `cancelled`

**Response (200):**
```json
{
  "message": "Delivery status updated successfully."
}
```

---

## System Flow

```
Website Order Flow:
Customer → POST /api/orders → Save Order → PATCH /ready → Queue Job → Uber Direct API

Uber Eats Flow:
Customer → Uber Eats App → POST /webhook/uber-eats/orders → Save Order

Delivery Status Flow:
Uber Direct → POST /webhook/uber-direct/status → Update delivery_status
```

---

## Uber Direct Credentials Setup

1. Go to **https://direct.uber.com** and sign up
2. Go to **Dashboard → Developer Tab**
3. Copy `Client ID`, `Client Secret`, `Customer ID`
4. Generate access token:

```bash
curl --request POST 'https://auth.uber.com/oauth/v2/token' \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'client_id=YOUR_CLIENT_ID' \
  --data-urlencode 'client_secret=YOUR_CLIENT_SECRET' \
  --data-urlencode 'grant_type=client_credentials' \
  --data-urlencode 'scope=eats.deliveries'
```

> Set `UBER_DIRECT_TEST_MODE=true` in `.env` for local development without real credentials.

---

## Testing

### Run all tests

```bash
php artisan test
```

### Run specific test suite

```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit
```

### Expected output

```
PASS  Tests\Unit\OrderRepositoryTest
PASS  Tests\Unit\OrderServiceTest
PASS  Tests\Unit\UberDirectServiceTest
PASS  Tests\Unit\UberAuthServiceTest
PASS  Tests\Feature\OrderApiTest
PASS  Tests\Feature\UberEatsWebhookTest
PASS  Tests\Feature\UberDirectWebhookTest

Tests: 46 passed
```

---

## Bonus Features

| Feature | Implementation |
|---------|---------------|
| Retry logic | `Http::retry(3, 500)` + Job `backoff([30, 60, 120])` |
| Logging | `Log::info` / `Log::error` throughout all services |
| Webhook auth | HMAC SHA256 signature verification |
| Queue worker | `DispatchUberDirectDelivery` Job with Supervisor support |

---

## Production Deployment (Supervisor)

```ini
[program:restaurant-delivery-worker]
command=php /var/www/restaurant-system/artisan queue:work database --queue=deliveries --tries=3 --timeout=30
autostart=true
autorestart=true
numprocs=2
stdout_logfile=/var/www/restaurant-system/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start restaurant-delivery-worker:*
```

---

## License

MIT
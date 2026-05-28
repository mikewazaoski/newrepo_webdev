# Mobile API (React Native)

**Production:** `https://newrepowebdev-production.up.railway.app`

**Local dev:** `http://localhost:8080` (Docker) or `http://10.0.2.2:8080` (Android emulator → host machine)

All mobile JSON responses use:

```json
{ "status": "success", ... }
```

or

```json
{ "status": "error", "message": "..." }
```

## Authentication

1. Register: `POST /api/register`
2. Verify email: `POST /api/verify-email` with `{ "token": "..." }`
3. Login: `POST /api/login` with `{ "email", "password" }`

Store the `token` from login/verify responses. Send on protected requests:

```
Authorization: Bearer <token>
```

Tokens are HMAC-signed, valid for **7 days**. Legacy unsigned tokens still work until they expire.

### Login response

```json
{
  "status": "success",
  "token": "...",
  "user": { "id", "email", "username", "name", "roles", "isVerified", "isActive" },
  "customer": { "id", "name", "email", "phone", "customerName", "username", "orderCount" }
}
```

If email is not verified: HTTP 403 with `requiresVerification: true`.

---

## Auth & account

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/register` | No | Create account |
| POST | `/api/verify-email` | No | Verify email, returns token + customer |
| POST | `/api/resend-verification` | No | `{ "email" }` |
| POST | `/api/login` | No | Login, returns token + customer |
| POST | `/api/mobile/google-auth` | No | `{ "email", "name" }` from Google |
| GET | `/api/mobile/profile` | Bearer | User + customer profile |
| POST | `/api/mobile/customer/sync` | Bearer | Create/link customer record |

---

## Customer (mobile)

| Method | Path | Auth | Body | Description |
|--------|------|------|------|-------------|
| GET | `/api/mobile/customer` | Bearer | — | Get customer profile |
| PUT | `/api/mobile/customer` | Bearer | `{ "name?", "phone?", "customerName?" }` | Update profile |
| POST | `/api/mobile/customer/sync` | Bearer | — | Ensure customer exists |
| GET | `/api/mobile/customer/orders` | Bearer | — | Order history (grouped by orderRef) |

### Update profile example

```http
PUT /api/mobile/customer
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Alice Johnson",
  "phone": "+1-555-0101",
  "customerName": "Alice"
}
```

---

## Catalog (public)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/mobile/health` | DB health check |
| GET | `/api/mobile/products` | Product list with `imageUrl` |
| GET | `/api/mobile/categories` | Category list |
| GET | `/api/mobile/payment-methods` | COD payment option |

---

## Orders

| Method | Path | Auth | Body |
|--------|------|------|------|
| GET | `/api/mobile/orders` | Bearer | — |
| POST | `/api/mobile/orders` | Bearer | See below |

### Create order

```json
{
  "paymentMethod": "cod",
  "items": [
    { "productId": 1, "quantity": 2 }
  ]
}
```

---

## Contact (public)

| Method | Path | Body |
|--------|------|------|
| POST | `/api/mobile/contact` | `{ "name", "email", "message" }` |

---

## React Native fetch helper

```javascript
const API_BASE = 'https://newrepowebdev-production.up.railway.app';

export async function api(path, { method = 'GET', token, body } = {}) {
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const json = await res.json();
  if (!res.ok || json.status === 'error') {
    throw new Error(json.message || json.error || 'Request failed');
  }
  return json;
}

// After login:
// const { token, customer } = await api('/api/login', { method: 'POST', body: { email, password } });
// const profile = await api('/api/mobile/customer', { token });
```

---

## Recommended app flow

1. `POST /api/register` → show “check email”
2. `POST /api/verify-email` with token from deep link → store `token`
3. Or `POST /api/login` → store `token`
4. `GET /api/mobile/profile` → show user + customer
5. `GET /api/mobile/products` → shop
6. `POST /api/mobile/orders` → checkout
7. `GET /api/mobile/customer/orders` → order history

Staff/admin ApiPlatform routes (`/api/customers`) require admin credentials and are **not** for the mobile app.

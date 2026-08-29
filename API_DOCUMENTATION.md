# Finance App — Complete API Documentation

**Generated:** 2026-08-29  
**Laravel Version:** 11 | **Auth:** Laravel Sanctum (Bearer Token)

| Environment | Base URL |
|-------------|----------|
| **Production (Live)** | `https://finance.risodev.com/expense` |
| Local (development) | `http://localhost/Laravel/finance-app/public` |

---

## Table of Contents

1. [Global Conventions](#global-conventions)
2. [API v1 — Mobile Expense App](#api-v1--mobile-expense-app)
   - [Auth & User](#1-auth--user)
   - [Settings & Static Data](#2-settings--static-data)
   - [Bootstrap](#3-bootstrap)
   - [Categories](#4-categories)
   - [Expenses](#5-expenses)
   - [Incomes](#6-incomes)
   - [Reports & Dashboard](#7-reports--dashboard)
   - [Meta & Import](#8-meta--import)
3. [Finance Web App API](#finance-web-app-api)
   - [Auth](#web-auth)
   - [Dashboard](#web-dashboard)
   - [Income](#web-income)
   - [Expense](#web-expense)
   - [Bank Account](#web-bank)
   - [Credit Card](#web-credit-card)
   - [Loan](#web-loan)
   - [Investment](#web-investment)
   - [Commission](#web-commission)
   - [Cashback](#web-cashback)
   - [Bad Debt](#web-bad-debt)
   - [Contact / Ledger](#web-contact)
   - [Reminder](#web-reminder)
   - [Report](#web-report)
   - [Settings / Masters](#web-settings)
4. [Error Reference](#error-reference)
5. [Quick Reference Table](#quick-reference-table)

---

## Global Conventions

### Base URLs

| API | Production URL | Local URL |
|-----|---------------|-----------|
| **Mobile App (v1)** | `https://finance.risodev.com/expense/api/v1` | `http://localhost/Laravel/finance-app/public/api/v1` |
| **Finance Web App** | `https://finance.risodev.com/expense/api` | `http://localhost/Laravel/finance-app/public/api` |

### Request Headers (all requests)

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>   ← (not needed for public endpoints)
```

### Authentication

- Login / Register returns a **Sanctum personal access token**
- Send it on every protected request: `Authorization: Bearer 1|abc123...`
- On 401 → session expired → force logout the user

### Amount Format (v1 Mobile API only)

- All money is **integer paise/cents** — never floats
- ₹123.45 → `12345`, ₹1000 → `100000`
- `budget_minor = null` means **no budget** — never use `0`

### Response Envelope (v1)

```json
// Single resource
{ "data": { "id": 1, ... } }

// List
{ "data": [ {...}, {...} ] }

// No content → HTTP 204 (empty body)
```

### Standard Error Response

```json
// 422 Validation
{
  "message": "The name field is required.",
  "errors": { "name": ["Give the category a name."] }
}

// 401 Unauthenticated
{ "message": "Unauthenticated." }

// 404 Not found
{ "message": "Not found." }

// 409 Conflict
{ "message": "...", "code": "category_in_use", "expense_count": 12 }
```

---

## API v1 — Mobile Expense App

Base URL: **`/api/v1`**

---

### 1. Auth & User

#### POST `/register` — Create account
Public endpoint. Seeds 8 default categories on registration.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "Secret123"
}
```

**Validation:**
- `email` — required, valid email, unique → *"An account with that email already exists."*
- `password` — min 8 chars, must have ≥1 letter AND ≥1 digit → *"Use at least 8 characters." / "Include at least one letter and one number."*

**Response — 201:**
```json
{
  "data": {
    "token": "1|abcdef123456...",
    "user": {
      "id": 1,
      "name": "",
      "email": "user@example.com",
      "onboarded": true,
      "has_pin": false,
      "currency": "INR",
      "theme_mode": "light"
    }
  }
}
```

**Side effect:** Creates 8 default categories for this user:

| # | Name | Icon | Color |
|---|------|------|-------|
| 0 | Food & Drink | 🍔 | #FF6B6B |
| 1 | Groceries | 🛒 | #2DD4BF |
| 2 | Transport | 🚗 | #38BDF8 |
| 3 | Shopping | 🛍 | #A78BFA |
| 4 | Bills & Utilities | 💡 | #FBBF24 |
| 5 | Health | 💊 | #34D399 |
| 6 | Entertainment | 🎬 | #F472B6 |
| 7 | Other | ✨ | #94A3B8 |

---

#### POST `/login` — Login
Public. Rate limited: 5 per minute.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "Secret123"
}
```

**Response — 200:** Same shape as `/register`

**Error cases:**
- Unknown email → 401 `{ "message": "No account uses that email." }`
- Wrong password → 401 `{ "message": "Incorrect password." }`

---

#### POST `/logout` — Logout 🔒
Revokes only the current token. Does NOT delete user data.

**Response — 204** (no body)

---

#### GET `/me` — Get current user 🔒
Call this on app startup to restore session state.

**Response — 200:**
```json
{
  "data": {
    "id": 1,
    "name": "Rishi",
    "email": "user@example.com",
    "onboarded": true,
    "has_pin": true,
    "currency": "INR",
    "theme_mode": "system"
  }
}
```

---

#### PUT `/me/password` — Change password 🔒

**Request:**
```json
{
  "current_password": "OldPass123",
  "new_password": "NewPass456"
}
```

- `current_password` wrong → 422 *"Your current password is not right."*
- `new_password` — same rules as register
- PIN is **kept** on password change (only cleared on password reset)

**Response — 204**

---

#### PATCH `/me/profile` — Update name & email 🔒

**Request:**
```json
{
  "name": "Rishi Dubey",
  "email": "newemail@example.com"
}
```

- `name` — max 40, trimmed, empty string allowed
- `email` — valid format, unique (ignores self)

**Response — 200:**
```json
{ "data": { "id": 1, "name": "Rishi Dubey", "email": "newemail@example.com", ... } }
```

---

#### POST `/me/onboarded` — Mark onboarding done 🔒
No request body.

**Response — 204**

---

#### PUT `/me/pin` — Set 4-digit PIN 🔒

**Request:**
```json
{ "pin": "1234" }
```

- Exactly 4 digits → else 422 *"Enter all four digits."*
- Stored as bcrypt hash — never plain text

**Response — 204**

---

#### DELETE `/me/pin` — Remove PIN 🔒
No request body.

**Response — 204**

---

#### POST `/forgot-password` — Send reset email
Public. Always returns 200 (doesn't reveal if email exists).

**Request:**
```json
{ "email": "user@example.com" }
```

**Response — 200:**
```json
{ "message": "If an account with that email exists, a reset link has been sent." }
```

---

#### POST `/reset-password` — Reset password via token
Public. On success: revokes all tokens + clears PIN.

**Request:**
```json
{
  "email": "user@example.com",
  "token": "abc123tokenFromEmail",
  "password": "NewPass456"
}
```

**Response — 204**

---

### 2. Settings & Static Data

#### PUT `/settings/currency` — Change currency 🔒

**Request:**
```json
{ "currency": "USD" }
```

**Valid codes (exactly 9):** `INR`, `USD`, `EUR`, `GBP`, `JPY`, `AUD`, `CAD`, `AED`, `SGD`

> Changing currency does NOT convert amounts — display preference only.

**Response — 204**

---

#### PUT `/settings/theme` — Change theme 🔒

**Request:**
```json
{ "theme_mode": "dark" }
```

**Valid values:** `light`, `dark`, `system`

**Response — 204**

---

#### GET `/currencies` — List supported currencies
Public endpoint.

**Response — 200:**
```json
{
  "data": [
    { "code": "INR", "symbol": "₹",   "decimals": 2, "label": "Indian Rupee" },
    { "code": "USD", "symbol": "$",   "decimals": 2, "label": "US Dollar" },
    { "code": "EUR", "symbol": "€",   "decimals": 2, "label": "Euro" },
    { "code": "GBP", "symbol": "£",   "decimals": 2, "label": "British Pound" },
    { "code": "JPY", "symbol": "¥",   "decimals": 0, "label": "Japanese Yen" },
    { "code": "AUD", "symbol": "A$",  "decimals": 2, "label": "Australian Dollar" },
    { "code": "CAD", "symbol": "C$",  "decimals": 2, "label": "Canadian Dollar" },
    { "code": "AED", "symbol": "AED", "decimals": 2, "label": "UAE Dirham" },
    { "code": "SGD", "symbol": "S$",  "decimals": 2, "label": "Singapore Dollar" }
  ]
}
```

> JPY uses `decimals: 0` — display without decimal places

---

### 3. Bootstrap

#### GET `/bootstrap` — App startup payload 🔒
Single call that returns user + categories together. Use on app launch.

**Response — 200:**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Rishi",
      "email": "user@example.com",
      "onboarded": true,
      "has_pin": false,
      "currency": "INR",
      "theme_mode": "light"
    },
    "categories": [
      {
        "id": 1,
        "user_id": 1,
        "name": "Food & Drink",
        "icon": "🍔",
        "color": "#FF6B6B",
        "budget_minor": 500000,
        "sort_order": 0,
        "created_at": "2026-08-29T07:44:44.000000Z",
        "updated_at": "2026-08-29T07:44:44.000000Z"
      }
    ]
  }
}
```

---

### 4. Categories

**Category object shape:**
```json
{
  "id": 3,
  "user_id": 1,
  "name": "Transport",
  "icon": "🚗",
  "color": "#38BDF8",
  "budget_minor": null,
  "sort_order": 2,
  "created_at": "...",
  "updated_at": "..."
}
```

**Ordering:** Always returned `sort_order ASC, name ASC`

---

#### GET `/categories` — List all categories 🔒

**Response — 200:**
```json
{ "data": [ ...category objects... ] }
```

---

#### POST `/categories` — Create category 🔒

**Request:**
```json
{
  "name": "Travel",
  "icon": "✈️",
  "color": "#38BDF8",
  "budget_minor": null
}
```

**Validation:**
- `name` — required, trimmed, max 40, unique per user (case-insensitive) → *"Give the category a name." / "A category with that name already exists."*
- `icon` — required string, max 16 chars (emoji)
- `color` — required, format `#RRGGBB`
- `budget_minor` — nullable integer, must be > 0 if set (`null` = no budget, `0` is invalid) → *"Budget must be a number greater than zero, or left empty."*

> `sort_order` is auto-assigned (MAX+1) — client cannot set it

**Response — 201:**
```json
{ "data": { ...category object... } }
```

---

#### GET `/categories/{id}` — Get single category 🔒
Returns category with `expense_count` (all-time count). Used before delete confirmation.

**Response — 200:**
```json
{
  "data": {
    "id": 3,
    "name": "Transport",
    "icon": "🚗",
    "color": "#38BDF8",
    "budget_minor": null,
    "sort_order": 2,
    "expense_count": 47
  }
}
```

---

#### PUT `/categories/{id}` — Update category 🔒
Same body + validation as create. Does NOT change `sort_order`.

**Request:**
```json
{
  "name": "Transport Updated",
  "icon": "🚌",
  "color": "#0EA5E9",
  "budget_minor": 300000
}
```

**Response — 200:**
```json
{ "data": { ...updated category... } }
```

---

#### DELETE `/categories/{id}?move_to={otherId}` — Delete category 🔒

**Flow:**
1. Fetch category via `GET /categories/{id}` to get `expense_count`
2. Show confirm dialog to user
3. Call DELETE with `?move_to=<other_category_id>` if there are expenses

**Rules:**
- Cannot delete user's last category → 422 *"You need at least one category — create another before deleting this one."*
- Has expenses but no `move_to` → 409:
  ```json
  { "message": "...", "code": "category_in_use", "expense_count": 12 }
  ```
- With `move_to`: all expenses refile to target category in a transaction
- `move_to` must belong to the same user

**Response — 204**

---

#### PATCH `/categories/{id}/budget` — Update budget only 🔒
Does not touch name/icon/color.

**Request:**
```json
{ "budget_minor": 250000 }
```
```json
{ "budget_minor": null }
```

**Response — 200:**
```json
{ "data": { ...category with updated budget... } }
```

---

#### PATCH `/categories/budgets` — Bulk update budgets 🔒
⚠️ Register this route BEFORE `{id}` routes (already done in code).

**Request:**
```json
{
  "budgets": [
    { "id": 1, "budget_minor": 500000 },
    { "id": 2, "budget_minor": null },
    { "id": 4, "budget_minor": 150000 }
  ]
}
```

**Response — 200:**
```json
{ "data": [ ...all categories in standard order... ] }
```

---

### 5. Expenses

**Expense list item shape (joined with category):**
```json
{
  "id": 10,
  "amount_minor": 45000,
  "category_id": 3,
  "note": "Auto to office",
  "spent_at": "2026-08-27",
  "category_name": "Transport",
  "category_icon": "🚗",
  "category_color": "#38BDF8"
}
```

**Ordering:** `spent_at DESC, id DESC`

---

#### GET `/expenses?month=YYYY-MM` — Month expenses 🔒
Returns the full month — no pagination.

**Query:** `?month=2026-08`

**Range logic:** `spent_at >= 2026-08-01 AND spent_at < 2026-09-01`

**Response — 200:**
```json
{ "data": [ ...expense list items... ] }
```

---

#### POST `/expenses` — Create expense 🔒

**Request:**
```json
{
  "amount_minor": 45000,
  "category_id": 3,
  "note": "Auto to office",
  "spent_at": "2026-08-27"
}
```

**Validation:**
- `amount_minor` — required integer > 0 → *"Enter an amount greater than zero."*
- `category_id` — required, must belong to this user → *"Pick a category for this expense."*
- `note` — nullable, max 120 chars, empty string saved as `null`
- `spent_at` — required `YYYY-MM-DD`, not in future (allow +1 day for timezone edge)

**Response — 201:**
```json
{ "data": { ...expense with joined category fields... } }
```

---

#### GET `/expenses/{id}` — Get single expense 🔒
Returns plain shape (no category join) — for edit prefill.

**Response — 200:**
```json
{
  "data": {
    "id": 10,
    "amount_minor": 45000,
    "category_id": 3,
    "note": "Auto to office",
    "spent_at": "2026-08-27"
  }
}
```

---

#### PUT `/expenses/{id}` — Update expense 🔒
Full-row replace — send all fields.

**Request:** Same as create

**Response — 200:**
```json
{ "data": { ...expense with joined category fields... } }
```

---

#### DELETE `/expenses/{id}` — Delete expense 🔒
Hard delete. No soft delete.

**Response — 204**

---

### 6. Incomes

**Income object shape:**
```json
{
  "id": 5,
  "user_id": 1,
  "amount_minor": 5000000,
  "source": "Salary",
  "note": null,
  "received_at": "2026-08-01",
  "created_at": "...",
  "updated_at": "..."
}
```

**Ordering:** `received_at DESC, id DESC`

---

#### GET `/incomes?month=YYYY-MM` — Month incomes 🔒
Full month, no pagination.

**Response — 200:**
```json
{ "data": [ ...income objects... ] }
```

---

#### POST `/incomes` — Create income 🔒

**Request:**
```json
{
  "amount_minor": 5000000,
  "source": "Salary",
  "note": null,
  "received_at": "2026-08-01"
}
```

**Validation:**
- `amount_minor` — required integer > 0 → *"Enter an amount greater than zero."*
- `source` — required, trimmed, max 40, free text → *"Say where this income came from."*
  > Suggested values: Salary, Freelance, Business, Interest, Refund, Gift, Other — but any string is valid
- `note` — nullable, max 120, empty → `null`
- `received_at` — `YYYY-MM-DD`, not future

**Response — 201:**
```json
{ "data": { ...income object... } }
```

---

#### GET `/incomes/{id}` — Get income 🔒
**Response — 200:** `{ "data": { ...income... } }`

#### PUT `/incomes/{id}` — Update income 🔒
Same body as create. **Response — 200**

#### DELETE `/incomes/{id}` — Delete income 🔒
Hard delete. **Response — 204**

---

### 7. Reports & Dashboard

#### GET `/reports/month-summary?month=YYYY-MM` — Month summary 🔒
Used for the home hero widget.

**Response — 200:**
```json
{
  "data": {
    "month": "2026-08",
    "total_minor": 1234500,
    "expense_count": 42,
    "previous_month": "2026-07",
    "previous_total_minor": 1100000
  }
}
```

> `previous_total_minor = 0` → no baseline (show count instead of % delta client-side)

---

#### GET `/reports/category-totals?month=YYYY-MM` — Category totals 🔒
Returns ALL categories including zero-spend ones (LEFT JOIN semantics). Used for donut chart, budget bars, alerts.

**Response — 200:**
```json
{
  "data": [
    {
      "category_id": 1,
      "category_name": "Food & Drink",
      "category_icon": "🍔",
      "category_color": "#FF6B6B",
      "budget_minor": 500000,
      "total_minor": 421000,
      "count": 17
    },
    {
      "category_id": 6,
      "category_name": "Health",
      "category_icon": "💊",
      "category_color": "#34D399",
      "budget_minor": null,
      "total_minor": 0,
      "count": 0
    }
  ]
}
```

**Ordering:** `total_minor DESC, sort_order ASC`

---

#### GET `/reports/months?from=YYYY-MM&to=YYYY-MM` — Per-month totals 🔒
Zero-filled range. Both `from` and `to` are inclusive.

**Response — 200:**
```json
{
  "data": [
    { "month": "2026-03", "total_minor": 0 },
    { "month": "2026-04", "total_minor": 98000 },
    { "month": "2026-05", "total_minor": 145000 },
    { "month": "2026-06", "total_minor": 220000 },
    { "month": "2026-07", "total_minor": 110000 },
    { "month": "2026-08", "total_minor": 123450 }
  ]
}
```

---

#### GET `/reports/flow` — Income vs expense chart 🔒
Used for bar chart on Transactions + Stats screens.

**Query params:**
- `mode` — required: `weekly` | `monthly` | `yearly`
- `count` — optional, default 6, max 24
- `today` — required `YYYY-MM-DD` (send device's local date — avoid server timezone issues)

**Bucket logic:**
- `weekly` → Sunday-start weeks, key = Sunday's date
- `monthly` → calendar months, key = `YYYY-MM`
- `yearly` → calendar years, key = `YYYY`

**Response — 200:**
```json
{
  "data": [
    {
      "key": "2026-03",
      "start": "2026-03-01",
      "end": "2026-04-01",
      "income_minor": 5000000,
      "expense_minor": 1234500
    },
    {
      "key": "2026-04",
      "start": "2026-04-01",
      "end": "2026-05-01",
      "income_minor": 5000000,
      "expense_minor": 980000
    }
  ]
}
```

> Exactly `count` buckets, zero-filled. Oldest first.

---

#### GET `/reports/day-flow?limit=8` — Recent active days 🔒
Days with ANY transaction. No zero-fill.

**Response — 200:**
```json
{
  "data": [
    { "day": "2026-08-29", "income_minor": 0,       "expense_minor": 76000 },
    { "day": "2026-08-27", "income_minor": 5000000, "expense_minor": 45000 },
    { "day": "2026-08-25", "income_minor": 0,       "expense_minor": 30000 }
  ]
}
```

**Ordering:** `day DESC`

---

#### GET `/reports/lifetime` — All-time totals 🔒
Used for wallet balance and alerts "has income" check.

**Response — 200:**
```json
{
  "data": {
    "income_minor": 15000000,
    "expense_minor": 9876500
  }
}
```

> Wallet balance = `income_minor - expense_minor` (compute client-side)

---

#### GET `/dashboard?month=YYYY-MM` — Home composite 🔒
4-in-1 call. Use instead of calling 4 separate endpoints on every Home load.

**Query:** `?month=2026-08`

> ⚠️ Always send device's local month — never let server infer "current month"

**Response — 200:**
```json
{
  "data": {
    "month": "2026-08",
    "expenses": [
      {
        "id": 10,
        "amount_minor": 45000,
        "category_id": 3,
        "note": "Auto to office",
        "spent_at": "2026-08-27",
        "category_name": "Transport",
        "category_icon": "🚗",
        "category_color": "#38BDF8"
      }
    ],
    "month_summary": {
      "total_minor": 1234500,
      "expense_count": 42,
      "previous_total_minor": 1100000
    },
    "category_totals": [
      {
        "category_id": 1,
        "category_name": "Food & Drink",
        "category_icon": "🍔",
        "category_color": "#FF6B6B",
        "budget_minor": 500000,
        "total_minor": 421000,
        "count": 17
      }
    ],
    "lifetime": {
      "income_minor": 15000000,
      "expense_minor": 9876500
    }
  }
}
```

> `expenses` is the FULL month list — Home shows 5 but sums all for hero total

---

#### GET `/alerts?month=YYYY-MM` — Budget alerts 🔒

**Alert kinds:**
- `"over"` — total > budget (danger)
- `"near"` — total >= budget × 0.8 (warning)
- `"no-income"` — user has zero lifetime income
- `"no-budgets"` — no category has a budget set

**Response — 200:**
```json
{
  "data": [
    { "kind": "over",       "category_id": 3, "total_minor": 620000, "budget_minor": 500000 },
    { "kind": "near",       "category_id": 1, "total_minor": 430000, "budget_minor": 500000 },
    { "kind": "no-income" },
    { "kind": "no-budgets" }
  ]
}
```

> Home bell red-dot count = number of `"over"` alerts only

---

### 8. Meta & Import

#### GET `/meta` — App metadata 🔒
Used on About screen.

**Response — 200:**
```json
{
  "data": {
    "expense_count": 431,
    "category_count": 9,
    "api_version": "1.0.0"
  }
}
```

---

#### POST `/import` — One-time data migration 🔒
Upload existing local SQLite data on first login. Idempotent — can only run once per user.

**Request:**
```json
{
  "categories": [
    {
      "local_id": 1,
      "name": "Food & Drink",
      "icon": "🍔",
      "color": "#FF6B6B",
      "budget_minor": null,
      "sort_order": 0
    }
  ],
  "expenses": [
    {
      "amount_minor": 45000,
      "local_category_id": 1,
      "note": null,
      "spent_at": "2026-05-14"
    }
  ],
  "incomes": [
    {
      "amount_minor": 5000000,
      "source": "Salary",
      "note": null,
      "received_at": "2026-05-01"
    }
  ]
}
```

**Semantics:**
- **Category merge:** Match by case-insensitive name. Match → keep existing (server wins). No match → create new.
- **Expenses/Incomes:** Insert with mapped category IDs
- **Orphan expense** (local_category_id not in payload) → 422, reject entire batch
- **Already imported** → 409 `{ "message": "Import already completed.", "code": "already_imported" }`

**Response — 201:**
```json
{
  "data": {
    "category_map": { "1": 14, "2": 15, "3": 16 },
    "imported": {
      "categories_created": 2,
      "categories_merged": 6,
      "expenses": 431,
      "incomes": 12
    }
  }
}
```

> `category_map` keys are local IDs (strings), values are server IDs

---

---

## Finance Web App API

Base URL: **`/api`**  
All amounts in **decimal format** (not paise). Auth via Bearer token.

---

### Web Auth

#### POST `/auth/register`
```json
{ "name": "Rishi", "email": "r@r.com", "mobile": "9999999999", "password": "pass", "password_confirmation": "pass" }
```
**Response — 201:** `{ "message", "user", "token" }`

#### POST `/auth/login`
```json
{ "email": "r@r.com", "password": "pass" }
```
**Response — 200:** `{ "message", "user", "token" }`

#### POST `/auth/logout` 🔒
**Response — 200:** `{ "message": "Logged out successfully" }`

#### GET `/auth/me` 🔒
**Response — 200:** User object

---

### Web Dashboard

#### GET `/dashboard` 🔒
Returns all summary data for the current month.

**Response — 200:**
```json
{
  "summary": {
    "total_income": 50000.00,
    "total_expense": 20000.00,
    "net_savings": 30000.00,
    "bank_balance": 150000.00,
    "credit_outstanding": 5000.00,
    "loan_pending": 200000.00,
    "investment_value": 500000.00,
    "commission_received": 3000.00,
    "cashback_received": 500.00,
    "pending_receivables": 10000.00
  },
  "monthly_chart": [
    { "month": "Mar 2026", "income": 45000, "expense": 18000 }
  ],
  "category_expenses": [
    { "category": "Food", "total": 5000 }
  ],
  "upcoming_reminders": [...],
  "recent_incomes": [...],
  "recent_expenses": [...]
}
```

---

### Web Income

#### GET `/income` 🔒
Query: `?type=salary&month=8&year=2026` (all optional)  
**Response:** Paginated list (20/page)

#### POST `/income` 🔒
```json
{
  "type": "salary",
  "date": "2026-08-01",
  "amount": 50000.00,
  "payment_mode": "Bank Transfer",
  "company_name": "ABC Corp",
  "salary_month": "August 2026",
  "note": "Monthly salary"
}
```

**Income types:** `salary` | `lic_commission` | `business` | `received_from` | `other`

**Type-specific fields:**
| Type | Extra Fields |
|------|-------------|
| `salary` | `company_name`, `salary_month` |
| `lic_commission` | `client_name`, `policy_number`, `plan_name`, `commission_type` |
| `business` | `business_name` |
| `received_from` | `person_name`, `mobile_number`, `reason` |
| `other` | `category_name`, `description` |

**Response — 201:** Income object

#### GET `/income/{id}` 🔒 · PUT `/income/{id}` 🔒 · DELETE `/income/{id}` 🔒

---

### Web Expense

#### GET `/expense` 🔒
Query: `?category_id=1&month=8&year=2026`  
**Response:** Paginated list with category

#### POST `/expense` 🔒
```json
{
  "expense_category_id": 1,
  "date": "2026-08-15",
  "amount": 500.00,
  "payment_mode": "UPI",
  "description": "Groceries"
}
```
**Response — 201:** Expense with category

#### GET `/expense/{id}` 🔒 · PUT `/expense/{id}` 🔒 · DELETE `/expense/{id}` 🔒

---

### Web Bank

#### GET `/bank` 🔒 — List all bank accounts
#### POST `/bank` 🔒 — Create bank account
```json
{
  "bank_name": "SBI",
  "account_number_last4": "4321",
  "current_balance": 50000.00,
  "account_type": "Savings",
  "ifsc_code": "SBIN0001234",
  "notes": "Primary account"
}
```

#### GET `/bank/{id}` 🔒 — With transactions
#### PUT `/bank/{id}` 🔒 · DELETE `/bank/{id}` 🔒

#### POST `/bank/{id}/transaction` 🔒 — Add transaction
```json
{
  "type": "credit",
  "amount": 5000.00,
  "date": "2026-08-15",
  "description": "Salary credited"
}
```
> `type`: `credit` or `debit` — auto-updates `current_balance`

---

### Web Credit Card

#### GET `/credit-card` 🔒 · POST `/credit-card` 🔒
```json
{
  "card_name": "HDFC Regalia",
  "bank_name": "HDFC",
  "credit_limit": 200000.00,
  "outstanding_amount": 15000.00,
  "due_date_day": 15,
  "notes": ""
}
```

#### GET `/credit-card/{id}` 🔒 · PUT `/credit-card/{id}` 🔒 · DELETE `/credit-card/{id}` 🔒

#### POST `/credit-card/{id}/transaction` 🔒 — Add transaction
```json
{
  "type": "purchase",
  "amount": 3000.00,
  "date": "2026-08-20",
  "description": "Amazon order"
}
```
> `type`: `purchase` (increases outstanding) or `payment` (decreases outstanding)

---

### Web Loan

#### GET `/loan` 🔒 · POST `/loan` 🔒
```json
{
  "loan_name": "Home Loan",
  "bank_or_person_name": "SBI",
  "total_amount": 3000000.00,
  "interest_rate": 8.5,
  "emi_amount": 25000.00,
  "start_date": "2024-01-01",
  "end_date": "2044-01-01",
  "pending_amount": 2800000.00,
  "status": "active",
  "notes": ""
}
```

#### GET `/loan/{id}` 🔒 · PUT `/loan/{id}` 🔒 · DELETE `/loan/{id}` 🔒

#### POST `/loan/{id}/payment` 🔒 — Record EMI/payment
```json
{
  "amount": 25000.00,
  "payment_date": "2026-08-05",
  "payment_mode": "Auto Debit",
  "note": "August EMI"
}
```
> Auto-decrements `pending_amount`. Marks loan `closed` if pending reaches 0.

---

### Web Investment

#### GET `/investment` 🔒 · POST `/investment` 🔒
```json
{
  "investment_name": "NIFTY 50 Index Fund",
  "category": "Mutual Fund",
  "date": "2023-01-15",
  "amount_invested": 100000.00,
  "current_value": 125000.00,
  "maturity_date": null,
  "returns": 25000.00,
  "notes": "SIP"
}
```
Query filter: `?category=Mutual+Fund`

#### GET `/investment/{id}` 🔒 · PUT `/investment/{id}` 🔒 · DELETE `/investment/{id}` 🔒

---

### Web Commission

#### GET `/commission` 🔒 · POST `/commission` 🔒
```json
{
  "date": "2026-08-10",
  "source_name": "LIC",
  "client_name": "Ramesh Kumar",
  "product_name": "Jeevan Anand",
  "commission_amount": 5000.00,
  "status": "received",
  "notes": ""
}
```
`status`: `pending` | `received` · Filter: `?status=pending`

#### GET `/commission/{id}` 🔒 · PUT `/commission/{id}` 🔒 · DELETE `/commission/{id}` 🔒

---

### Web Cashback

#### GET `/cashback` 🔒 · POST `/cashback` 🔒
```json
{
  "date": "2026-08-12",
  "platform_name": "Amazon Pay",
  "amount": 250.00,
  "status": "received",
  "notes": "5% cashback on order"
}
```
`status`: `pending` | `received`

#### GET `/cashback/{id}` 🔒 · PUT `/cashback/{id}` 🔒 · DELETE `/cashback/{id}` 🔒

---

### Web Bad Debt

#### GET `/bad-debt` 🔒 · POST `/bad-debt` 🔒
```json
{
  "person_name": "Amit Sharma",
  "mobile_number": "9876543210",
  "amount": 10000.00,
  "date_given": "2026-05-01",
  "reason": "Medical emergency",
  "expected_return_date": "2026-09-01",
  "status": "pending",
  "received_amount": 0,
  "notes": ""
}
```
`status`: `pending` | `partial_received` | `received` | `written_off`

#### GET `/bad-debt/{id}` 🔒 · PUT `/bad-debt/{id}` 🔒 · DELETE `/bad-debt/{id}` 🔒

---

### Web Contact

Contacts/Ledger — track money lent/borrowed from people.

#### GET `/contact` 🔒 — List with balance
Response includes computed `balance` field (`lent - borrowed`)

#### POST `/contact` 🔒
```json
{ "name": "Rahul Verma", "mobile": "9876543210", "notes": "" }
```

#### GET `/contact/{id}` 🔒 — With transactions + balance
#### PUT `/contact/{id}` 🔒 · DELETE `/contact/{id}` 🔒

#### POST `/contact/{id}/transaction` 🔒 — Add transaction
```json
{
  "type": "lent",
  "date": "2026-08-15",
  "amount": 5000.00,
  "reason": "Festival loan",
  "status": "pending"
}
```
`type`: `lent` | `borrowed` · `status`: `pending` | `settled`

---

### Web Reminder

#### GET `/reminder` 🔒 · POST `/reminder` 🔒
```json
{
  "type": "loan_emi",
  "title": "Home Loan EMI",
  "due_date": "2026-09-05",
  "amount": 25000.00,
  "notes": "SBI account"
}
```
Filter: `?is_done=false`

#### GET `/reminder/{id}` 🔒 · PUT `/reminder/{id}` 🔒 · DELETE `/reminder/{id}` 🔒

#### PATCH `/reminder/{id}/done` 🔒 — Mark reminder complete
**Response — 200:** Updated reminder

---

### Web Report

#### GET `/report` 🔒
**Query params:**
- `type` — required: `income` | `expense` | `profit_loss` | `investment` | `loan` | `commission`
- `year` — required integer
- `month` — optional integer (1–12)

**Response — 200:**
```json
{
  "type": "income",
  "month": 8,
  "year": 2026,
  "records": [...],
  "total": 75000.00
}
```

---

### Web Settings

#### GET `/settings` 🔒 — All master data
```json
{
  "expense_categories": [...],
  "income_categories": [...],
  "payment_modes": [...],
  "investment_categories": [...],
  "bank_masters": [...]
}
```

#### POST `/settings/expense-category` 🔒
```json
{ "name": "Petrol", "group": "personal" }
```
`group`: `home` | `personal` | `business` | `other`

#### DELETE `/settings/expense-category/{id}` 🔒

#### POST `/settings/income-category` 🔒
```json
{ "name": "Dividends", "type": "investment" }
```

#### DELETE `/settings/income-category/{id}` 🔒

#### POST `/settings/payment-mode` 🔒
```json
{ "name": "Google Pay" }
```

#### DELETE `/settings/payment-mode/{id}` 🔒
> Cannot delete default payment modes

#### POST `/settings/investment-category` 🔒
```json
{ "name": "Real Estate" }
```

#### DELETE `/settings/investment-category/{id}` 🔒
> Cannot delete default categories

#### POST `/settings/bank-master` 🔒
```json
{ "name": "Axis Bank", "short_name": "AXIS" }
```

#### DELETE `/settings/bank-master/{id}` 🔒
> Cannot delete default banks

---

## Error Reference

| HTTP Status | When | Body |
|-------------|------|------|
| 200 | Success with body | `{ "data": ... }` |
| 201 | Resource created | `{ "data": ... }` |
| 204 | Success, no body | *(empty)* |
| 401 | Wrong credentials | `{ "message": "Incorrect password." }` |
| 401 | Missing/expired token | `{ "message": "Unauthenticated." }` |
| 403 | Access forbidden | `{ "message": "Forbidden." }` |
| 404 | Not found / other user's resource | `{ "message": "Not found." }` |
| 409 | Conflict (e.g. import already done) | `{ "message": "...", "code": "..." }` |
| 422 | Validation failure | `{ "message": "...", "errors": { "field": ["msg"] } }` |
| 429 | Rate limit exceeded | `{ "message": "Too Many Attempts." }` |

### Frontend error strings (exact — v1 mobile)
```
Enter a valid email address.
An account with that email already exists.
Use at least 8 characters.
Include at least one letter and one number.
No account uses that email.
Incorrect password.
Your current password is not right.
Enter all four digits.
Enter an amount greater than zero.
Pick a category for this expense.
Say where this income came from.
Give the category a name.
A category with that name already exists.
Budget must be a number greater than zero, or left empty.
You need at least one category — create another before deleting this one.
Import already completed.
```

---

## Quick Reference Table

### API v1 — Mobile App (42 endpoints)

**Live Base URL:** `https://finance.risodev.com/expense/api/v1`

| # | Method | Path | Auth | Description |
|---|--------|------|------|-------------|
| 1 | POST | `/api/v1/register` | Public | Register + seed categories |
| 2 | POST | `/api/v1/login` | Public | Login → token |
| 3 | POST | `/api/v1/logout` | 🔒 | Revoke current token |
| 4 | GET | `/api/v1/me` | 🔒 | Get current user |
| 5 | PUT | `/api/v1/me/password` | 🔒 | Change password |
| 6 | PATCH | `/api/v1/me/profile` | 🔒 | Update name + email |
| 7 | POST | `/api/v1/me/onboarded` | 🔒 | Mark onboarding done |
| 8 | PUT | `/api/v1/me/pin` | 🔒 | Set 4-digit PIN |
| 9 | DELETE | `/api/v1/me/pin` | 🔒 | Remove PIN |
| 10 | POST | `/api/v1/forgot-password` | Public | Send reset email |
| 11 | POST | `/api/v1/reset-password` | Public | Reset via token |
| 12 | PUT | `/api/v1/settings/currency` | 🔒 | Change currency |
| 13 | PUT | `/api/v1/settings/theme` | 🔒 | Change theme |
| 14 | GET | `/api/v1/currencies` | Public | 9 supported currencies |
| 15 | GET | `/api/v1/bootstrap` | 🔒 | Startup payload (user + categories) |
| 16 | GET | `/api/v1/categories` | 🔒 | List categories |
| 17 | POST | `/api/v1/categories` | 🔒 | Create category |
| 18 | GET | `/api/v1/categories/{id}` | 🔒 | Get category + expense_count |
| 19 | PUT | `/api/v1/categories/{id}` | 🔒 | Update category |
| 20 | DELETE | `/api/v1/categories/{id}?move_to=` | 🔒 | Delete + refile expenses |
| 21 | PATCH | `/api/v1/categories/{id}/budget` | 🔒 | Update budget only |
| 22 | PATCH | `/api/v1/categories/budgets` | 🔒 | Bulk update budgets |
| 23 | GET | `/api/v1/expenses?month=YYYY-MM` | 🔒 | Month expenses (full, no pagination) |
| 24 | POST | `/api/v1/expenses` | 🔒 | Create expense |
| 25 | GET | `/api/v1/expenses/{id}` | 🔒 | Get expense (edit prefill) |
| 26 | PUT | `/api/v1/expenses/{id}` | 🔒 | Update expense |
| 27 | DELETE | `/api/v1/expenses/{id}` | 🔒 | Delete expense |
| 28 | GET | `/api/v1/incomes?month=YYYY-MM` | 🔒 | Month incomes |
| 29 | POST | `/api/v1/incomes` | 🔒 | Create income |
| 30 | GET | `/api/v1/incomes/{id}` | 🔒 | Get income |
| 31 | PUT | `/api/v1/incomes/{id}` | 🔒 | Update income |
| 32 | DELETE | `/api/v1/incomes/{id}` | 🔒 | Delete income |
| 33 | GET | `/api/v1/reports/month-summary` | 🔒 | Month total + prev month |
| 34 | GET | `/api/v1/reports/category-totals` | 🔒 | Per-category totals (ALL cats) |
| 35 | GET | `/api/v1/reports/months` | 🔒 | Zero-filled monthly totals range |
| 36 | GET | `/api/v1/reports/flow` | 🔒 | Income vs expense chart buckets |
| 37 | GET | `/api/v1/reports/day-flow` | 🔒 | Recent active days |
| 38 | GET | `/api/v1/reports/lifetime` | 🔒 | All-time income + expense |
| 39 | GET | `/api/v1/dashboard` | 🔒 | Composite Home payload |
| 40 | GET | `/api/v1/alerts` | 🔒 | Budget alerts |
| 41 | GET | `/api/v1/meta` | 🔒 | Counts + API version |
| 42 | POST | `/api/v1/import` | 🔒 | One-time local data migration |

### Finance Web App API (60+ endpoints)

**Live Base URL:** `https://finance.risodev.com/expense/api`

| Module | Method | Path | Description |
|--------|--------|------|-------------|
| Auth | POST | `/api/auth/register` | Register |
| Auth | POST | `/api/auth/login` | Login |
| Auth | POST | `/api/auth/logout` | Logout 🔒 |
| Auth | GET | `/api/auth/me` | Current user 🔒 |
| Dashboard | GET | `/api/dashboard` | Full dashboard data 🔒 |
| Income | GET/POST | `/api/income` | List / Create 🔒 |
| Income | GET/PUT/DELETE | `/api/income/{id}` | Show / Update / Delete 🔒 |
| Expense | GET/POST | `/api/expense` | List / Create 🔒 |
| Expense | GET/PUT/DELETE | `/api/expense/{id}` | Show / Update / Delete 🔒 |
| Bank | GET/POST | `/api/bank` | List / Create 🔒 |
| Bank | GET/PUT/DELETE | `/api/bank/{id}` | Show / Update / Delete 🔒 |
| Bank | POST | `/api/bank/{id}/transaction` | Add transaction 🔒 |
| Credit Card | GET/POST | `/api/credit-card` | List / Create 🔒 |
| Credit Card | GET/PUT/DELETE | `/api/credit-card/{id}` | Show / Update / Delete 🔒 |
| Credit Card | POST | `/api/credit-card/{id}/transaction` | Add transaction 🔒 |
| Loan | GET/POST | `/api/loan` | List / Create 🔒 |
| Loan | GET/PUT/DELETE | `/api/loan/{id}` | Show / Update / Delete 🔒 |
| Loan | POST | `/api/loan/{id}/payment` | Record payment 🔒 |
| Investment | GET/POST | `/api/investment` | List / Create 🔒 |
| Investment | GET/PUT/DELETE | `/api/investment/{id}` | Show / Update / Delete 🔒 |
| Commission | GET/POST | `/api/commission` | List / Create 🔒 |
| Commission | GET/PUT/DELETE | `/api/commission/{id}` | Show / Update / Delete 🔒 |
| Cashback | GET/POST | `/api/cashback` | List / Create 🔒 |
| Cashback | GET/PUT/DELETE | `/api/cashback/{id}` | Show / Update / Delete 🔒 |
| Bad Debt | GET/POST | `/api/bad-debt` | List / Create 🔒 |
| Bad Debt | GET/PUT/DELETE | `/api/bad-debt/{id}` | Show / Update / Delete 🔒 |
| Contact | GET/POST | `/api/contact` | List / Create 🔒 |
| Contact | GET/PUT/DELETE | `/api/contact/{id}` | Show / Update / Delete 🔒 |
| Contact | POST | `/api/contact/{id}/transaction` | Add lent/borrowed 🔒 |
| Reminder | GET/POST | `/api/reminder` | List / Create 🔒 |
| Reminder | GET/PUT/DELETE | `/api/reminder/{id}` | Show / Update / Delete 🔒 |
| Reminder | PATCH | `/api/reminder/{id}/done` | Mark done 🔒 |
| Report | GET | `/api/report` | Generate report 🔒 |
| Settings | GET | `/api/settings` | All master data 🔒 |
| Settings | POST | `/api/settings/expense-category` | Add expense category 🔒 |
| Settings | DELETE | `/api/settings/expense-category/{id}` | Delete expense category 🔒 |
| Settings | POST | `/api/settings/income-category` | Add income category 🔒 |
| Settings | DELETE | `/api/settings/income-category/{id}` | Delete income category 🔒 |
| Settings | POST | `/api/settings/payment-mode` | Add payment mode 🔒 |
| Settings | DELETE | `/api/settings/payment-mode/{id}` | Delete payment mode 🔒 |
| Settings | POST | `/api/settings/investment-category` | Add investment category 🔒 |
| Settings | DELETE | `/api/settings/investment-category/{id}` | Delete investment category 🔒 |
| Settings | POST | `/api/settings/bank-master` | Add bank master 🔒 |
| Settings | DELETE | `/api/settings/bank-master/{id}` | Delete bank master 🔒 |

---

*🔒 = Requires `Authorization: Bearer <token>` header*

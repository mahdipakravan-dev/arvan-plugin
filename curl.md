# ArvanCloud CDN Plan API — Request & Response Reference

Complete developer reference for the endpoints under the **Plan** tag in ArvanCloud CDN API v4.

> Generated from ArvanCloud's official OpenAPI specification, version **4.115.7**, on **2026-08-22**. The OpenAPI document defines shapes and constraints but provides few concrete response examples. Example payloads in this README are therefore schema-valid illustrations, not guaranteed production payloads.

## Contents

- [API basics](#api-basics)
- [Authentication](#authentication)
- [Plan levels](#plan-levels)
- [Endpoint summary](#endpoint-summary)
- [1. List plan definitions](#1-list-plan-definitions)
- [2. List plan definitions for a domain](#2-list-plan-definitions-for-a-domain)
- [3. Update a domain plan](#3-update-a-domain-plan)
- [4. Get plan violations](#4-get-plan-violations)
- [5. Get plan usages and estimated cost](#5-get-plan-usages-and-estimated-cost)
- [Response models](#response-models)
- [Errors](#errors)
- [TypeScript models](#typescript-models)
- [Implementation notes](#implementation-notes)

## API basics

| Item | Value |
|---|---|
| Base URL | `https://napi.arvancloud.ir/cdn/4.0` |
| Content type | `application/json` |
| API specification | OpenAPI 3.0.0 |
| Spec version inspected | `4.115.7` |

All examples use these shell variables:

```bash
export ARVAN_API_KEY='your-machine-user-api-key'
export DOMAIN='example.com'
```

Never expose an API key in frontend code, a public repository, logs, screenshots, or browser storage. Call this API from a trusted backend.

## Authentication

The API declares two alternative security schemes globally:

1. `UserToken`: HTTP Bearer token (JWT)
2. `ApiKey`: an API key passed in the `Authorization` header

For Machine User/API-key authentication, ArvanCloud documentation commonly uses this header format:

```http
Authorization: apikey YOUR_API_KEY
```

Example:

```bash
curl --request GET \
  --url 'https://napi.arvancloud.ir/cdn/4.0/plans' \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json'
```

For a user JWT, use standard Bearer syntax:

```http
Authorization: Bearer YOUR_JWT
```

## Plan levels

The API represents a plan as an integer from `0` through `4`:

| Level | API description | Key commonly returned in plan data |
|---:|---|---|
| `0` | Traffic | `paygo` or traffic/pay-as-you-go context |
| `1` | Basic | `basic` |
| `2` | Growth | `growth` |
| `3` | Professional | `professional` |
| `4` | Enterprise | `enterprise` |

Important: the OpenAPI description states that **subdomains require Growth (`2`) or higher**.

## Endpoint summary

| Method | Path | Operation ID | Purpose |
|---|---|---|---|
| `GET` | `/plans` | `plans.index` | List feature definitions across plan sets; optionally contextualized by a domain |
| `GET` | `/domains/{domain}/plans` | `domains.plans` | List plan feature definitions for a particular domain |
| `PUT` | `/domains/{domain}/plan` | `domains.plans.update` | Change a domain's plan |
| `GET` | `/domains/{domain}/plan/violations` | `domains.plans.violations` | Find features/configurations that conflict with plan levels |
| `GET` | `/domains/{domain}/plan/usages` | `domains.plans.usages` | Get feature usage and estimated cost, optionally for a target plan |

---

## 1. List plan definitions

```http
GET /plans
```

Returns plan metadata and feature definitions, grouped into feature sets.

### Query parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `domain` | No | hostname or UUID | Domain name or domain ID used to contextualize plan data | `example.com` |
| `ignored_plans` | No | string | Comma-separated plan levels to omit | `0,1` |

### Request

```bash
curl --get \
  --url 'https://napi.arvancloud.ir/cdn/4.0/plans' \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json' \
  --data-urlencode "domain=${DOMAIN}" \
  --data-urlencode 'ignored_plans=0,1'
```

### Success response

**Status:** `200 OK`

**Body:** [`PlanResponse`](#planresponse)

```json
{
  "message": null,
  "data": {
    "currency": {
      "key": "irr",
      "label": "IRR"
    },
    "plans": [
      {
        "key": "enterprise",
        "name": "Enterprise",
        "monthly_cost": 0,
        "discount": 0,
        "needed_balance": 0
      }
    ],
    "feature_sets": [
      {
        "id": "example-set",
        "label": "Example feature set",
        "features": [
          {
            "id": "example-feature",
            "plans": {
              "0": null,
              "1": {
                "meta": {
                  "labels": ["Example", true],
                  "tip": "Example plan note",
                  "available_params": []
                },
                "usage_limit": {
                  "min": 0,
                  "max": 100
                },
                "pricing": {
                  "free_tier": 10,
                  "flat": null,
                  "per_unit": {
                    "metric_key": "example_metric",
                    "currency": "IRR",
                    "value": 1000
                  }
                }
              },
              "2": null,
              "3": null,
              "4": null
            },
            "meta": {
              "label": "Example feature",
              "description": "Schema-valid illustrative feature"
            }
          }
        ]
      }
    ]
  }
}
```

The example above illustrates the schema only. Feature IDs, labels, costs, limits, and pricing values must be read from the live response.

---

## 2. List plan definitions for a domain

```http
GET /domains/{domain}/plans
```

Returns the same [`PlanResponse`](#planresponse) shape as `GET /plans`, scoped to a domain.

### Path parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `domain` | Yes | hostname | Domain name | `example.com` |

### Query parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `ignored_plans` | No | string | Comma-separated plan levels to omit | `0,1` |

### Request

```bash
curl --get \
  --url "https://napi.arvancloud.ir/cdn/4.0/domains/${DOMAIN}/plans" \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json' \
  --data-urlencode 'ignored_plans=0,1'
```

### Success response

**Status:** `200 OK`

**Body:** [`PlanResponse`](#planresponse). See the full example under [List plan definitions](#1-list-plan-definitions).

---

## 3. Update a domain plan

```http
PUT /domains/{domain}/plan
```

Changes the selected plan of a CDN domain.

### Path parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `domain` | Yes | hostname | Domain name | `example.com` |

### Request body

Content type: `application/json`

| Field | Required | Type | Constraints | Description |
|---|---:|---|---|---|
| `plan_level` | Yes | integer | Minimum `0`, maximum `4` | Desired plan level |

```json
{
  "plan_level": 2
}
```

### Request

```bash
curl --request PUT \
  --url "https://napi.arvancloud.ir/cdn/4.0/domains/${DOMAIN}/plan" \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"plan_level":2}'
```

### Success response

**Status:** `200 OK`

The endpoint references the shared `OK` response, whose body is a `MessageResponse`:

```json
{
  "message": "Plan updated successfully"
}
```

The message text is illustrative; clients should not depend on an exact phrase.

### Documented errors

| Status | Meaning |
|---:|---|
| `401` | Missing or invalid access token/API key |
| `404` | Domain/resource not found |
| `422` | Invalid input or a business/validation rule prevents the change |

Before downgrading, call the [violations endpoint](#4-get-plan-violations). For estimating financial impact, call the [usages endpoint](#5-get-plan-usages-and-estimated-cost) with `target_plan`.

---

## 4. Get plan violations

```http
GET /domains/{domain}/plan/violations
```

Returns feature/configuration violations grouped by plan. This is especially useful before allowing a customer to switch to a lower plan.

### Path parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `domain` | Yes | hostname | Domain name | `example.com` |

### Request

```bash
curl --request GET \
  --url "https://napi.arvancloud.ir/cdn/4.0/domains/${DOMAIN}/plan/violations" \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json'
```

### Success response

**Status:** `200 OK`

```json
{
  "message": null,
  "data": {
    "violations": {
      "paygo": ["feature-id-a"],
      "basic": ["feature-id-a", "feature-id-b"],
      "growth": [],
      "professional": [],
      "enterprise": []
    }
  }
}
```

Each array contains strings. The specification does not further define whether every string is always a feature ID, code, or human-readable message. Treat the values as opaque identifiers/text and display them defensively unless verified against a live response.

### Documented errors

| Status | Meaning |
|---:|---|
| `401` | Missing or invalid access token/API key |
| `404` | Domain/resource not found |

---

## 5. Get plan usages and estimated cost

```http
GET /domains/{domain}/plan/usages
```

Returns feature-level usage, per-feature estimated costs, and a total estimated cost. An optional `target_plan` lets the server calculate data in the context of a desired plan.

### Path parameters

| Name | Required | Type | Description | Example |
|---|---:|---|---|---|
| `domain` | Yes | hostname | Domain name | `example.com` |

### Query parameters

| Name | Required | Type | Constraints | Description |
|---|---:|---|---|---|
| `target_plan` | No | integer | `0` through `4` | Plan level for the usage/cost calculation |

### Request

```bash
curl --get \
  --url "https://napi.arvancloud.ir/cdn/4.0/domains/${DOMAIN}/plan/usages" \
  --header "Authorization: apikey ${ARVAN_API_KEY}" \
  --header 'Accept: application/json' \
  --data-urlencode 'target_plan=2'
```

### Success response

**Status:** `200 OK`

```json
{
  "message": null,
  "data": {
    "feature_usages": [
      {
        "feature_id": "example-feature",
        "pricing": {
          "free_tier": 10,
          "flat": null,
          "per_unit": {
            "metric_key": "example_metric",
            "currency": "IRR",
            "value": 1000
          }
        },
        "estimated_cost": {
          "period": "monthly",
          "currency": "IRT",
          "value": 25000
        },
        "usage": 35
      }
    ],
    "estimated_cost": {
      "period": "monthly",
      "currency": "IRT",
      "value": 25000
    }
  }
}
```

The numeric values and feature identifier are illustrative. Note that the official schema uses different currency enums in different models: plan-definition currency keys are `irr`/`eur`, while `EstimatedCost.currency` is `IRT`/`EUR`.

The specification contains `dayly` (not `daily`) as a valid `EstimatedCost.period` enum value. Clients should preserve and accept the documented spelling:

```text
monthly | dayly | hourly
```

### Documented errors

| Status | Meaning |
|---:|---|
| `401` | Missing or invalid access token/API key |
| `404` | Domain/resource not found |

---

## Response models

### PlanResponse

```text
PlanResponse
├── message: string | null
└── data: FeatureSets | null
    ├── currency: Currency
    ├── plans: PlanInfo[]
    └── feature_sets: FeatureSet[]
```

### Currency

| Field | Type | Allowed values / notes |
|---|---|---|
| `key` | string | `irr`, `eur` |
| `label` | string | Display label |

### PlanInfo

| Field | Type | Notes |
|---|---|---|
| `key` | string | Example in spec: `enterprise` |
| `name` | string | Human-readable plan name |
| `monthly_cost` | number | Monthly price/cost |
| `discount` | number | Percentage from `0` to `100` according to description |
| `needed_balance` | number | Required account balance for selecting the plan |

### FeatureSet

| Field | Type | Notes |
|---|---|---|
| `id` | string | Set identifier |
| `label` | string | Display label |
| `features` | `FeatureDefinition[]` | Features in this set |

### FeatureDefinition

| Field | Type | Notes |
|---|---|---|
| `id` | string | Feature identifier |
| `plans` | object | Keys `0`, `1`, `2`, `3`, `4`; values may be `null` |
| `meta.label` | string | Display label |
| `meta.description` | string | Feature description |

### FeaturePlanDefinition

The object itself may be `null` for a plan.

| Field | Type | Notes |
|---|---|---|
| `meta.labels` | `(string \| boolean)[]` | Labels can be either strings or booleans |
| `meta.tip` | string | Supporting tip |
| `meta.available_params` | `object[]` | Shape is not further constrained in the spec |
| `usage_limit` | `UsageLimit \| null` | Min/max usage boundaries |
| `pricing` | `FeaturePricing \| null` | Pricing details |

### UsageLimit

| Field | Type |
|---|---|
| `min` | integer |
| `max` | integer |

The whole `UsageLimit` value may be `null`.

### FeaturePricing

| Field | Type | Notes |
|---|---|---|
| `free_tier` | integer | Included/free usage quantity |
| `flat` | `FeaturePrice \| null` | Flat charge |
| `per_unit` | `FeaturePrice \| null` | Per-unit charge |

The whole `FeaturePricing` value may be `null`.

### FeaturePrice

| Field | Type | Notes |
|---|---|---|
| `metric_key` | string | Billing metric identifier |
| `currency` | string | Not enum-constrained in this model |
| `value` | number | Price value |

The whole `FeaturePrice` value may be `null`.

### Violations

```json
{
  "violations": {
    "paygo": ["string"],
    "basic": ["string"],
    "growth": ["string"],
    "professional": ["string"],
    "enterprise": ["string"]
  }
}
```

### Usages

| Field | Type |
|---|---|
| `feature_usages` | `FeatureUsage[]` |
| `estimated_cost` | `EstimatedCost` |

### FeatureUsage

| Field | Type |
|---|---|
| `feature_id` | string |
| `pricing` | `FeaturePricing \| null` |
| `estimated_cost` | `EstimatedCost` |
| `usage` | number |

### EstimatedCost

| Field | Type | Allowed values |
|---|---|---|
| `period` | string | `monthly`, `dayly`, `hourly` |
| `currency` | string | `IRT`, `EUR` |
| `value` | number | — |

## Errors

### 401 Unauthorized

The official description is “Access token is missing or invalid.”

```json
{
  "message": "Unauthenticated."
}
```

Message text may differ.

### 404 Not Found

```json
{
  "status": false,
  "message": "Resource not found"
}
```

The shared schema defines `message` and adds a `status` property defaulting to `false`.

### 422 Unprocessable Entity

Documented for plan updates. General shape:

```json
{
  "status": false,
  "message": "The given data was invalid.",
  "errors": {
    "plan_level": ["The selected plan level is invalid."]
  }
}
```

`errors` is polymorphic in the official spec and can be one of several string-array, array, nested-array, or object schemas. Do not assume it is always `Record<string, string[]>` without normalizing it first.

The two plan-list endpoints do not explicitly enumerate error responses in the inspected OpenAPI path definitions. Authentication still applies globally, so clients must handle authentication and generic HTTP failures for them as well.

## TypeScript models

These types intentionally keep all schema properties optional because the OpenAPI schemas define properties but generally do not declare them in `required` arrays.

```ts
export type PlanLevel = 0 | 1 | 2 | 3 | 4;
export type PlanKey = "paygo" | "basic" | "growth" | "professional" | "enterprise";

export interface MessageResponse {
  message?: string;
}

export interface DataWithMessageResponse<T> {
  message?: string | null;
  data?: T | null;
}

export interface Currency {
  key?: "irr" | "eur";
  label?: string;
}

export interface PlanInfo {
  key?: string;
  name?: string;
  monthly_cost?: number;
  discount?: number;
  needed_balance?: number;
}

export interface FeaturePrice {
  metric_key?: string;
  currency?: string;
  value?: number;
}

export interface UsageLimit {
  min?: number;
  max?: number;
}

export interface FeaturePricing {
  free_tier?: number;
  flat?: FeaturePrice | null;
  per_unit?: FeaturePrice | null;
}

export interface FeaturePlanDefinition {
  meta?: {
    labels?: Array<string | boolean>;
    tip?: string;
    available_params?: Array<Record<string, unknown>>;
  };
  usage_limit?: UsageLimit | null;
  pricing?: FeaturePricing | null;
}

export interface FeatureDefinition {
  id?: string;
  plans?: Partial<Record<`${PlanLevel}`, FeaturePlanDefinition | null>>;
  meta?: {
    label?: string;
    description?: string;
  };
}

export interface FeatureSet {
  id?: string;
  label?: string;
  features?: FeatureDefinition[];
}

export interface FeatureSets {
  currency?: Currency;
  plans?: PlanInfo[];
  feature_sets?: FeatureSet[];
}

export type PlanResponse = DataWithMessageResponse<FeatureSets>;

export interface PlanUpdateRequest {
  plan_level: PlanLevel;
}

export interface Violations {
  violations?: Partial<Record<PlanKey, string[]>>;
}

export interface EstimatedCost {
  period?: "monthly" | "dayly" | "hourly";
  currency?: "IRT" | "EUR";
  value?: number;
}

export interface FeatureUsage {
  feature_id?: string;
  pricing?: FeaturePricing | null;
  estimated_cost?: EstimatedCost;
  usage?: number;
}

export interface Usages {
  feature_usages?: FeatureUsage[];
  estimated_cost?: EstimatedCost;
}

export type ViolationsResponse = DataWithMessageResponse<Violations>;
export type UsagesResponse = DataWithMessageResponse<Usages>;

export interface ApiError {
  status?: boolean;
  message?: string;
  errors?: unknown;
}
```

### Minimal fetch helper

```ts
const CDN_BASE_URL = "https://napi.arvancloud.ir/cdn/4.0";

async function arvanRequest<T>(
  path: string,
  apiKey: string,
  init: RequestInit = {},
): Promise<T> {
  const response = await fetch(`${CDN_BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      Authorization: `apikey ${apiKey}`,
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...init.headers,
    },
  });

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = new Error(`ArvanCloud API returned HTTP ${response.status}`);
    Object.assign(error, { status: response.status, payload });
    throw error;
  }

  return payload as T;
}

export function getDomainPlans(domain: string, apiKey: string) {
  return arvanRequest<PlanResponse>(
    `/domains/${encodeURIComponent(domain)}/plans`,
    apiKey,
  );
}

export function updateDomainPlan(
  domain: string,
  planLevel: PlanLevel,
  apiKey: string,
) {
  return arvanRequest<MessageResponse>(
    `/domains/${encodeURIComponent(domain)}/plan`,
    apiKey,
    {
      method: "PUT",
      body: JSON.stringify({ plan_level: planLevel } satisfies PlanUpdateRequest),
    },
  );
}
```

## Implementation notes

1. **Use a backend proxy.** Store the Machine User API key server-side and never return it to the browser.
2. **Validate plan levels locally.** Only accept integers `0`–`4`; apply the Growth-or-higher rule to subdomains where relevant.
3. **Check violations before downgrading.** Present violations to the customer and block or confirm the transition according to your business flow.
4. **Show the estimated financial effect.** Call `/plan/usages?target_plan=N` before confirmation.
5. **Do not hard-code prices or feature availability.** Render plan cards and comparison data from the live `/plans` response.
6. **Treat money carefully.** Preserve the currency returned by the API. Do not infer that `irr`, `IRR`, and `IRT` mean the same unit; the schemas use `irr` and `IRT` in different contexts.
7. **Be null-safe and forward-compatible.** Several nested pricing, limit, and plan-definition objects are nullable, and most properties are not formally required.
8. **Treat feature identifiers as opaque.** Do not create business logic from undocumented feature ID naming patterns.
9. **Normalize errors.** The `422.errors` field has multiple possible shapes.
10. **Do not rely on response messages.** Use HTTP status and structured data for application logic.

## Recommended plan-change flow

1. Load `/domains/{domain}/plans` for current, domain-specific availability.
2. Ask the customer to select a target `plan_level`.
3. Load `/domains/{domain}/plan/violations` and inspect the target plan's array.
4. Load `/domains/{domain}/plan/usages?target_plan={level}` to display estimated cost.
5. Ask for explicit confirmation.
6. Send `PUT /domains/{domain}/plan`.
7. Refresh the domain and plan data from the API; do not rely only on optimistic local state.

## Sources

- [Official ArvanCloud CDN API v4 Plan documentation](https://www.arvancloud.ir/api/cdn/4.0#tag/Plan)
- [Official CDN v4 OpenAPI specification](https://cdn-docs.s3.ir-thr-at1.arvanstorage.ir/v4.openapi.yml)
- [ArvanCloud API usage and authentication guide](https://docs.arvancloud.ir/en/developer-tools/api/api-usage)
- [ArvanCloud CDN Go SDK](https://github.com/arvancloud/cdn-go)

## Scope

This README covers only the operations tagged **Plan** in CDN API v4. It does not document domain creation, billing/payment APIs, DNS, cache, WAF, or other CDN endpoints.
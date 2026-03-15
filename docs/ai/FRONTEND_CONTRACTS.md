# FRONTEND CONTRACTS

## Purpose

This document freezes the Inertia payload shape exposed by Laravel to the current frontend pages.

Date format used by the current backend:

- `YYYY-MM-DD HH:mm`
- nullable dates are returned as `null`

## Dashboard

### Component

- `Dashboard`

### Props

- `stats`: required object
- `recentReplies`: required array
- `recentAlerts`: required array
- `scheduledSends`: required array

### stats

- `sentToday`: required integer
- `dailyLimit`: required integer
- `healthStatus`: required enum `good|degraded|critical`
- `bounceRate`: required integer
- `activeCampaigns`: required integer
- `scheduledCount`: required integer

### recentReplies[]

- `id`: required integer
- `status`: required string, currently always `replied`
- `from`: required string
- `subject`: required string
- `time`: nullable string

### recentAlerts[]

- `id`: required integer
- `status`: required enum `auto_replied|soft_bounced|hard_bounced|failed`
- `email`: required string
- `detail`: required string
- `time`: nullable string

### scheduledSends[]

- `id`: required integer
- `subject`: required string
- `recipientCount`: required integer
- `scheduledAt`: nullable string

## Contacts

### Component

- `Contacts/Index`

### Props

- `contacts`: required array
- `filters`: required object (echo of active query params — used to hydrate filter state on page load)

### filters (Contacts)

- `search`: required string (empty string when not active)
- `status`: required enum `all|active|bounced|unsubscribed`
- `score`: required enum `all|engaged|interested|warm|cold|excluded`

### contacts[]

- `id`: required integer
- `firstName`: required string
- `lastName`: required string
- `title`: nullable string
- `organization`: nullable string
- `email`: required string
- `score`: required integer
- `scoreLevel`: required enum `cold|warm|interested|engaged|excluded`
- `excluded`: required boolean
- `unsubscribed`: required boolean
- `lastActivityAt`: nullable string

## Organizations

### Component

- `Organizations/Index`

### Props

- `organizations`: required array
- `filters`: required object (echo of active query params — used to hydrate filter state on page load)

### filters (Organizations)

- `search`: required string (empty string when not active)

### organizations[]

- `id`: required integer
- `name`: required string
- `domain`: nullable string
- `contactCount`: required integer
- `sentCount`: required integer
- `lastActivityAt`: nullable string

## Drafts

### Component

- `Drafts/Index`

### Props

- `drafts`: required array

### drafts[]

- `id`: required integer
- `subject`: required string
- `recipientCount`: required integer
- `type`: required enum `single|multiple`
- `status`: required string
- `scheduledAt`: nullable string
- `updatedAt`: nullable string

## Templates

### Component

- `Templates/Index`

### Props

- `templates`: required array

### templates[]

- `id`: required integer
- `name`: required string
- `subject`: required string
- `active`: required boolean
- `usageCount`: required integer
- `updatedAt`: nullable string

## Campaigns

### Component

- `Campaigns/Index`

### Props

- `campaigns`: required array

### campaigns[]

- `id`: required integer
- `name`: required string
- `status`: required string
- `progressPercent`: required integer
- `recipientCount`: required integer
- `openCount`: required integer
- `replyCount`: required integer
- `bounceCount`: required integer
- `scheduledAt`: nullable string
- `updatedAt`: nullable string

# React Admin Chat Integration

Last updated: 2026-08-22

> Chat broadcasts and Firebase delivery are
> processed synchronously. No queue worker is required for these features.
>
> Trade-off: the send-message request waits for notification delivery attempts.
> Slow Pusher or Firebase responses may increase API response time.
> The message remains stored if an external notification service fails.

## 1. Purpose and Scope

This guide explains how to integrate DarCare’s Laravel chat and notification backend into a **React admin dashboard**.

### What the admin dashboard chat supports

| Capability | Confirmed backend support |
|---|---|
| Customer ↔ Admin support chat (`support_customer`) | Yes — admin can list, open, send, close, reopen |
| Provider ↔ Admin support chat (`support_provider`) | Yes — admin can list, open, send, close, reopen |
| Inspect request conversations (`request`) | Yes — via `/api/v1/admin/chat/*` with `adminInspect` policy (read + list filters). Sending in request chats is **not** allowed for admins unless they somehow become participants (they do not by default) |
| Real-time message delivery via Pusher | Yes — `.MessageSent` on `private-conversation.{id}` |
| Live chat unread fan-out | Yes — `.UnreadCountUpdated` on typed user channels |
| Database notification inbox APIs | Yes — `/api/v1/notifications` for **non-chat** notifications only |
| FCM device registration for web | Optional — `platform: web` is accepted by device-token API |

### What this guide does not cover

- Building the customer or provider Flutter apps (see `docs/FLUTTER_CHAT_INTEGRATION_2026-08-22.md`)
- Laravel server, queue, Pusher, or Firebase credential setup
- Legacy request-scoped message routes (`/api/v1/requests/{id}/messages`) — deprecated; admin should not use them

### Document labels

| Label | Meaning |
|---|---|
| **Confirmed backend behavior** | Verified from Laravel source in this repository |
| **Recommended frontend implementation** | Suggested React patterns; not enforced by the API |
| **Assumption requiring verification** | Could not be confirmed from this repo (for example, a separate admin React app was not present) |

---

## 2. Confirmed Backend Contract

### Base API prefix

| Item | Value |
|---|---|
| API base path | `/api/v1` |
| Full example | `https://api.example.com/api/v1/...` |
| Health check | `GET /up` (outside `/api/v1`) |

### Authentication method

| Item | Confirmed value |
|---|---|
| Auth | Laravel Sanctum personal access tokens |
| Admin login | `POST /api/v1/auth/admin/login` |
| Request auth header | `Authorization: Bearer YOUR_SANCTUM_TOKEN` |
| Preferred Accept header | `Accept: application/json` |
| Admin gate middleware | `auth:sanctum` + `admin` (`EnsureAdmin`: actor must be `User` with `role = admin`) |
| Token expiration | Sanctum `expiration` is `null` (tokens do not auto-expire by config) |

Admin login body:

```json
{
  "email": "admin@example.com",
  "password": "YOUR_PASSWORD"
}
```

Admin login success envelope (`data`):

```json
{
  "token": "YOUR_SANCTUM_TOKEN",
  "admin": {
    "id": 1,
    "name": "Admin Name",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

### Broadcast authentication endpoint

| Item | Confirmed value |
|---|---|
| Endpoint | `POST /api/broadcasting/auth` |
| Middleware | `api`, `auth:sanctum` |
| Client requirement | Bearer Sanctum token + `Accept: application/json` |

Configured in `bootstrap/app.php` via `withBroadcasting(..., ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])`.

### Pusher channels

| Laravel channel registration | Client subscription name | Who can authorize |
|---|---|---|
| `conversation.{conversationId}` | `private-conversation.{conversationId}` | Active participant, request owner/assigned provider, or admin for support / request inspect |
| `user.user.{userId}` | `private-user.user.{userId}` | Only the matching `User` (customer or admin) |
| `user.provider.{providerId}` | `private-user.provider.{providerId}` | Only the matching `Provider` |

Admin actors are `User` morph type `user`, so the admin inbox channel is:

```text
private-user.user.{adminId}
```

### Event names

Laravel Echo listens with a leading dot when `broadcastAs()` is set.

| Event class | `broadcastAs` | Channel | Currently dispatched? |
|---|---|---|---|
| `MessageSent` | `MessageSent` | `conversation.{id}` | **Yes** — after message persist (`ShouldBroadcastNow`) |
| `MessageRead` | `MessageRead` | `conversation.{id}` | **No** — class + payload exist, but mark-read does not broadcast |
| `ConversationUpdated` | `ConversationUpdated` | `conversation.{id}` | **No** — class + payload exist, but close/reopen do not broadcast |
| `NotificationCreated` | `NotificationCreated` | typed `user.*` channel | **No** for chat (non-chat database notifications only) |
| `UnreadCountUpdated` | `UnreadCountUpdated` | typed `user.*` channel | **Yes** — from synchronous `MessageNotificationService` |

There are **no** `ConversationCreated` or `ConversationClosed` event classes in the current codebase.

#### Confirmed `.MessageSent` payload

```json
{
  "id": 101,
  "client_message_id": "uuid-or-null",
  "conversation_id": 12,
  "sender": {
    "type": "user",
    "id": 5,
    "display_role": "support"
  },
  "type": "text",
  "body": "Hello",
  "reply_to": null,
  "created_at": "2026-07-21T12:00:00+00:00"
}
```

Notes:

- `sender.type` is morph alias `user` or `provider` (not display role).
- Admin senders use `display_role: "support"`.
- Soft-deleted bodies are `null` in broadcast/resource payloads.

#### `.NotificationCreated` (non-chat only)

Chat messages **do not** emit `.NotificationCreated`. This event applies only to
non-chat database notifications (service requests, admin announcements, etc.).
Historical chat rows may still appear in `GET /api/v1/notifications` until
optionally cleaned up.

```json
{
  "notification": {
    "id": "uuid",
    "type": "chat_message",
    "title": "New message",
    "body": "preview...",
    "data": { "...": "..." },
    "route": "chat",
    "conversation_id": 12,
    "message_id": 101,
    "service_request_id": null,
    "read_at": null,
    "is_read": false,
    "created_at": "..."
  }
}
```

#### Confirmed `.UnreadCountUpdated` payload

```json
{
  "conversation_id": 12,
  "unread": {
    "total": 3,
    "by_type": {
      "request": 0,
      "support_customer": 2,
      "support_provider": 1
    }
  },
  "conversation_unread": 2
}
```

**Admin caveat (confirmed):** unread totals are computed from `conversation_participants` rows. Admins are **not** added as participants when support conversations are opened. Therefore `GET /api/v1/chat/unread-count` and participant-based unread badges are typically **0 for admins**. Prefer notification inbox + conversation list refresh for admin UX.

#### Defined but not currently broadcast: `.MessageRead`

```json
{
  "conversation_id": 12,
  "reader": { "type": "user", "id": 5 },
  "last_read_message_id": 101,
  "read_at": "2026-07-21T12:01:00+00:00"
}
```

#### Defined but not currently broadcast: `.ConversationUpdated`

```json
{
  "id": 12,
  "type": "support_customer",
  "status": "closed",
  "last_message_at": "2026-07-21T12:00:00+00:00",
  "closed_at": "2026-07-21T12:05:00+00:00"
}
```

### Admin chat endpoints

All require `auth:sanctum` + `admin`.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/chat/conversations` | List/filter conversations |
| `GET` | `/api/v1/admin/chat/conversations/{conversation}` | Open one conversation |
| `GET` | `/api/v1/admin/chat/conversations/{conversation}/messages` | Cursor-paginated messages |
| `POST` | `/api/v1/admin/chat/conversations/{conversation}/messages` | Send message (throttled `chat-send`) |
| `POST` | `/api/v1/admin/chat/conversations/{conversation}/close` | Close support conversation |
| `POST` | `/api/v1/admin/chat/conversations/{conversation}/reopen` | Reopen support conversation |

Admin list query parameters (all optional):

| Query | Behavior |
|---|---|
| `type` | Exact conversation type (`request`, `support_customer`, `support_provider`) |
| `status` | Exact status (`open`, `closed`, `read_only`) |
| `service_request` | Filter by `service_request_id` |
| `customer` | Active participant `user` id |
| `provider` | Active participant `provider` id |
| `search` | Message body `LIKE` search |
| `cursor` | Laravel cursor pagination cursor |

### Shared chat endpoints useful to admin

| Method | Endpoint | Admin notes |
|---|---|---|
| `GET` | `/api/v1/chat/conversations` | For admins, backend lists only `support_customer` + `support_provider` |
| `GET` | `/api/v1/chat/unread-count` | Auth required; admin totals usually 0 (see caveat above) |
| `PATCH` | `/api/v1/chat/conversations/{id}/read` | Policy allows admin on support; service no-ops without participant row |
| `PATCH` | `/api/v1/chat/conversations/{id}/mute` | Requires participant / request-party access — typically not useful for admin |

### Notification endpoints

| Method | Endpoint | Auth |
|---|---|---|
| `GET` | `/api/v1/notifications` | Sanctum |
| `PATCH` | `/api/v1/notifications/read-all` | Sanctum |
| `PATCH` | `/api/v1/notifications/{notification}/read` | Sanctum (owner only) |
| `DELETE` | `/api/v1/notifications/{notification}` | Sanctum (owner only) |
| `POST` | `/api/v1/admin/notifications/send-bulk` | Sanctum + admin (bulk marketing/ops, not chat-specific) |

Notification list query:

| Query | Behavior |
|---|---|
| `unread=1` / `unread=true` | Only unread (`read_at` null) |
| `per_page` | Page size (default `15`) |
| `page` | Laravel page number |

### Pagination type

| Resource | Style | Page size | Cursor/page param |
|---|---|---|---|
| Admin conversations | Cursor | 20 | `cursor` |
| Actor conversations | Cursor | default 15, max 50 | `cursor` |
| Messages (admin + actor) | Cursor | 50 | `cursor` |
| Notifications | Offset/page | default 15 | `page`, `per_page` |

Cursor list response `data` shape (admin conversations/messages):

```json
{
  "data": [ /* resources */ ],
  "next_cursor": "eyJ...encoded...",
  "has_more": true
}
```

Actor conversation list also includes `prev_cursor`.

### API response envelope

Success:

```json
{
  "success": true,
  "message": "Success",
  "data": {},
  "errors": null
}
```

Created (201) uses the same envelope with a created message.

Error (trait / middleware style):

```json
{
  "success": false,
  "message": "Forbidden. Admin access required.",
  "data": null,
  "errors": null
}
```

Validation errors (Laravel default for API JSON): typically HTTP `422` with `message` + `errors` object. Rate limit (`429`) is customized in `bootstrap/app.php`:

```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "data": null,
  "errors": null
}
```

### Rate limits (confirmed)

| Limiter | Limit | Applied to |
|---|---|---|
| `chat-send` | 30/min per actor | Send message |
| `chat-read` | 120/min per actor | Mark read |
| `chat-open` | 20/min per actor | Open conversation (`POST /chat/conversations`) |
| `device-tokens` | 20/min per actor | Device token register/delete |
| `admin-bulk-notifications` | 5/min per actor | Bulk send |

### Actor / role mapping (confirmed)

| Actor | Storage | Morph alias | Safe display role |
|---|---|---|---|
| Customer | `users.role = user` | `user` | `customer` |
| Admin | `users.role = admin` | `user` | `support` |
| Artisan/provider | `providers` table | `provider` | `artisan` |

Conversation types: `request`, `support_customer`, `support_provider`  
Conversation statuses: `open`, `closed`, `read_only`

---

## 3. Required React Packages

### Assumption requiring verification

A dedicated React admin dashboard repository was **not** present in this Laravel workspace. This repo only contains Laravel Vite scaffolding (`resources/js/echo.js`) with `axios`, `laravel-echo`, and `pusher-js` already listed in root `package.json`.

### Recommended packages (examples for a Vite + React admin app)

```bash
npm install axios laravel-echo pusher-js
npm install react-router-dom
```

| Package | Why |
|---|---|
| `axios` | HTTP client already used by Laravel Vite scaffold |
| `laravel-echo` | Subscribes to Laravel-named broadcast events |
| `pusher-js` | Pusher transport required by Echo |
| `react-router-dom` | Example routing for inbox ↔ conversation screens |

Do **not** force Redux/Zustand/React Query if the admin app already has a state library — adapt the hooks below to that stack.

If the admin app is TypeScript (recommended), no extra chat-specific packages are required beyond types for the libraries above.

---

## 4. Environment Variables

Use the Vite `VITE_` prefix (this Laravel app uses Vite; a separate CRA/Next admin app should use its own public prefix instead).

```env
VITE_API_BASE_URL=https://api.example.com
VITE_PUSHER_APP_KEY=YOUR_PUSHER_APP_KEY
VITE_PUSHER_APP_CLUSTER=YOUR_PUSHER_CLUSTER
VITE_PUSHER_AUTH_ENDPOINT=https://api.example.com/api/broadcasting/auth
```

### Never put these in the React app

| Secret | Where it belongs |
|---|---|
| `PUSHER_APP_SECRET` | Laravel server only |
| `PUSHER_APP_ID` | Laravel server only (not required by browser Echo) |
| `FIREBASE_CREDENTIALS` / service-account JSON | Laravel server only |
| Database credentials / `APP_KEY` | Laravel server only |

React may use the **public** Pusher key + cluster only.

---

## 5. Authentication Integration

### Confirmed backend behavior

- Admin obtains a Sanctum token from `POST /api/v1/auth/admin/login`.
- Admin routes require that token and `role=admin`.
- Private channel auth uses the same Bearer token against `/api/broadcasting/auth`.
- Logout endpoint: `POST /api/v1/auth/logout` (optional `device_token` body field invalidates that FCM token only).

### Recommended frontend implementation

1. Store the Sanctum token in memory + a secure httpOnly cookie when possible.
2. If the existing admin app already uses `localStorage`/`sessionStorage`, continue consistently, but treat XSS as a token-theft risk.
3. Attach `Authorization: Bearer <token>` on every API and Echo auth request.
4. On `401`, clear token, disconnect Echo, redirect to login.
5. On logout: call logout API, disconnect Echo, clear local chat/notification state.

### Axios client example (TypeScript)

```ts
import axios, { AxiosError } from 'axios';

const API_BASE = import.meta.env.VITE_API_BASE_URL;

export const apiClient = axios.create({
  baseURL: `${API_BASE}/api/v1`,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

export function setAuthToken(token: string | null) {
  if (token) {
    apiClient.defaults.headers.common.Authorization = `Bearer ${token}`;
  } else {
    delete apiClient.defaults.headers.common.Authorization;
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Recommended: trigger logout / redirect
    }
    return Promise.reject(error);
  }
);
```

Never log the Bearer token.

---

## 6. Laravel Echo and Pusher Setup

### Confirmed backend behavior

- Default broadcaster env is `BROADCAST_CONNECTION=pusher`.
- Echo’s `private('conversation.12')` automatically targets `private-conversation.12`.
- Auth endpoint is `/api/broadcasting/auth` (absolute URL recommended for a separate admin origin).

### Recommended Echo factory

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

window.Pusher = Pusher;

export function createEcho(token: string) {
  return new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: import.meta.env.VITE_PUSHER_AUTH_ENDPOINT,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  });
}

export function disconnectEcho(echo: Echo | null) {
  if (!echo) return;
  echo.disconnect();
}
```

### Reconnection / cleanup (recommended)

- Keep one Echo instance per authenticated session.
- On logout or token rotation, `echo.leave(...)` all channels then `echo.disconnect()`.
- After Pusher reconnect, refetch conversation list, open conversation messages, and notifications (see §17). There is no dedicated “messages since id” endpoint.

---

## 7. Admin API Client

All methods below are **confirmed** against `AdminConversationController`, `ConversationController`, and `NotificationController`.

### List admin conversations

- **Method:** `GET`
- **Endpoint:** `/admin/chat/conversations`
- **Query:** `type`, `status`, `service_request`, `customer`, `provider`, `search`, `cursor`
- **Auth:** admin
- **Response `data`:** `{ data: Conversation[], next_cursor: string|null, has_more: boolean }`

### Open a conversation

- **Method:** `GET`
- **Endpoint:** `/admin/chat/conversations/{id}`
- **Auth:** admin; support via `view`, request via `adminInspect`
- **Response:** single `Conversation` resource
- **Errors:** `403` if unauthorized; `404` if missing

### Fetch paginated messages

- **Method:** `GET`
- **Endpoint:** `/admin/chat/conversations/{id}/messages`
- **Query:** `cursor`
- **Order:** newest first (`created_at desc`, `id desc`), page size 50
- **Response `data`:** `{ data: Message[], next_cursor, has_more }`

### Send a message

- **Method:** `POST`
- **Endpoint:** `/admin/chat/conversations/{id}/messages`
- **Body fields:**

| Field | Required | Rules |
|---|---|---|
| `body` | yes | string, trimmed, max 5000 |
| `reply_to_message_id` | no | integer, must exist in `messages` |
| `client_message_id` | no | string, max 100; idempotent per sender+conversation |

- **Do not send** `sender_id` / `sender_type` — backend derives sender from Sanctum user.
- **Auth:** `sendMessage` policy — admin may send only when conversation status is `open` and type is support.
- **Success:** `201` + `Message` resource
- **Errors:** `403` closed/read_only/non-support send; `422` validation; `429` throttle

Idempotency: repeating the same `client_message_id` for the same admin returns the existing message (`201`) without creating a duplicate.

### Mark messages read

- **Method:** `PATCH`
- **Endpoint:** `/chat/conversations/{id}/read`
- **Body:** `{ "last_message_id": number | null }`
- **Admin note:** policy allows support access, but unread service returns early when no participant row exists — **no durable admin read cursor today**.

### Close support conversation

- **Method:** `POST`
- **Endpoint:** `/admin/chat/conversations/{id}/close`
- **Auth:** admin + support type only
- **Effect:** `status=closed`, `closed_at=now`
- **Realtime:** no `ConversationUpdated` broadcast currently — update UI from REST response

### Reopen support conversation

- **Method:** `POST`
- **Endpoint:** `/admin/chat/conversations/{id}/reopen`
- **Effect:** `status=open`, `closed_at=null`
- **Realtime:** none currently

### Get unread counts

- **Method:** `GET`
- **Endpoint:** `/chat/unread-count`
- **Response `data`:**

```json
{
  "total": 0,
  "by_type": {
    "request": 0,
    "support_customer": 0,
    "support_provider": 0
  }
}
```

### Notifications

| Action | Method | Endpoint | Body / query |
|---|---|---|---|
| List | `GET` | `/notifications` | `unread`, `per_page`, `page` |
| Mark one read | `PATCH` | `/notifications/{id}/read` | none |
| Mark all read | `PATCH` | `/notifications/read-all` | none |
| Delete | `DELETE` | `/notifications/{id}` | none |

Delete **is implemented**.

### TypeScript API examples

```ts
import { apiClient } from './apiClient';
import type {
  ApiEnvelope,
  Conversation,
  CursorPage,
  ChatMessage,
  UnreadCount,
  DatabaseNotification,
} from '../features/chat/types';

export async function listAdminConversations(params: Record<string, string | number | undefined>) {
  const { data } = await apiClient.get<ApiEnvelope<CursorPage<Conversation>>>(
    '/admin/chat/conversations',
    { params }
  );
  return data.data;
}

export async function getAdminConversation(id: number) {
  const { data } = await apiClient.get<ApiEnvelope<Conversation>>(`/admin/chat/conversations/${id}`);
  return data.data;
}

export async function listAdminMessages(id: number, cursor?: string) {
  const { data } = await apiClient.get<ApiEnvelope<CursorPage<ChatMessage>>>(
    `/admin/chat/conversations/${id}/messages`,
    { params: { cursor } }
  );
  return data.data;
}

export async function sendAdminMessage(
  id: number,
  body: { body: string; client_message_id?: string; reply_to_message_id?: number }
) {
  const { data } = await apiClient.post<ApiEnvelope<ChatMessage>>(
    `/admin/chat/conversations/${id}/messages`,
    body
  );
  return data.data;
}

export async function closeConversation(id: number) {
  const { data } = await apiClient.post<ApiEnvelope<Conversation>>(
    `/admin/chat/conversations/${id}/close`
  );
  return data.data;
}

export async function reopenConversation(id: number) {
  const { data } = await apiClient.post<ApiEnvelope<Conversation>>(
    `/admin/chat/conversations/${id}/reopen`
  );
  return data.data;
}

export async function getUnreadCount() {
  const { data } = await apiClient.get<ApiEnvelope<UnreadCount>>('/chat/unread-count');
  return data.data;
}

export async function listNotifications(params?: { unread?: boolean; page?: number; per_page?: number }) {
  const { data } = await apiClient.get<ApiEnvelope<unknown>>('/notifications', { params });
  return data.data;
}
```

---

## 8. Recommended React Folder Structure

**Assumption requiring verification:** adapt names to the admin app’s conventions.

```text
src/
  api/
    apiClient.ts
    chatApi.ts
    notificationApi.ts
    authApi.ts
  realtime/
    echoClient.ts
    chatSubscriptions.ts
  features/chat/
    components/
    hooks/
    pages/
    types/
    utils/
  features/notifications/
    components/
    hooks/
    types/
  auth/
    tokenStorage.ts
    AuthProvider.tsx
```

---

## 9. TypeScript Interfaces

Based on `ConversationResource`, `ParticipantResource`, `MessageResource`, `ServiceRequestSummaryResource`, and `NotificationResource`.

```ts
export type MorphActorType = 'user' | 'provider';
export type DisplayRole = 'customer' | 'artisan' | 'support';
export type ConversationType = 'request' | 'support_customer' | 'support_provider';
export type ConversationStatus = 'open' | 'closed' | 'read_only';

export interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface CursorPage<T> {
  data: T[];
  next_cursor: string | null;
  prev_cursor?: string | null;
  has_more: boolean;
}

export interface MessageSender {
  type: MorphActorType | string;
  id: number;
  display_role: DisplayRole | string;
}

export interface ChatMessage {
  id: number;
  conversation_id: number;
  service_request_id: number | null;
  client_message_id: string | null;
  type: string; // currently persisted as "text"
  body: string | null;
  deleted: boolean;
  sender: MessageSender;
  reply_to_message_id: number | null;
  created_at: string | null;
}

export interface ConversationParticipant {
  type: MorphActorType | string;
  id: number;
  display_role: DisplayRole | string;
  name: string | null;
  joined_at: string | null;
  left_at: string | null;
  muted: boolean;
  last_read_at: string | null;
}

export interface ServiceRequestSummary {
  id: number;
  status: string;
  urgency: string | null;
  description: string;
  provider_id: number | null;
  user_id: number;
}

export interface Conversation {
  id: number;
  type: ConversationType | string;
  status: ConversationStatus | string;
  service_request?: ServiceRequestSummary;
  participants: ConversationParticipant[];
  last_message?: ChatMessage;
  last_message_at: string | null;
  unread_count: number;
  muted: boolean;
  closed_at: string | null;
  created_at: string | null;
}

export interface UnreadCount {
  total: number;
  by_type: {
    request: number;
    support_customer: number;
    support_provider: number;
    [key: string]: number;
  };
}

export interface DatabaseNotification {
  id: string;
  type: string;
  title: string | null;
  body: string | null;
  data: Record<string, unknown>;
  route: string | null;
  conversation_id: number | null;
  message_id: number | null;
  service_request_id: number | null;
  read_at: string | null;
  is_read: boolean;
  created_at: string;
}
```

Do not invent fields such as attachments, typing flags, or assignment IDs — they are not in the resources.

---

## 10. Admin Chat UI

### Recommended screens

1. **Inbox** — filters (`type`, `status`, search), conversation rows, notification badge
2. **Conversation detail** — history, composer, close/reopen for support, read-only banner for `closed` / `read_only`
3. **Notifications drawer/page** — database notifications with deep links

### Component hierarchy (recommended)

```text
AdminChatPage
  ChatFilters
  ConversationList
    ConversationListItem
  ConversationPane
    ConversationHeader (identity + service request link + close/reopen)
    MessageList
      MessageBubble
      LoadOlderButton
    MessageComposer (disabled when status !== open)
  NotificationBell
    NotificationPanel
```

### UX notes tied to backend

- Request inspection: show messages; **do not** enable composer unless send policy would succeed (admin send is support-only while `open`).
- Support close/reopen: update local conversation from REST response.
- Empty/error/retry states should wrap list and message fetches separately.

---

## 11. Conversation List Flow

1. Fetch `GET /admin/chat/conversations`.
2. Render `last_message`, `status`, participant names, optional `service_request`.
3. Subscribe admin channel: `Echo.private('user.user.' + adminId)`.
4. On `.UnreadCountUpdated` / optional list refresh triggers, upsert conversation by id.
5. Reorder by `last_message_at` descending (matches backend ordering).
6. Avoid duplicates by conversation `id`.
7. On Pusher reconnect, refetch first page and merge.

**Confirmed:** there is no dedicated “conversation created” event. New support threads appear when a customer/provider opens support and later messages (notification fan-out), or when the admin refreshes the list.

---

## 12. Conversation Subscription Flow

```mermaid
sequenceDiagram
  participant Admin as React Admin
  participant API as Laravel API
  participant Pusher as Pusher Channels
  participant Mobile as Customer/Provider app

  Admin->>API: GET /api/v1/admin/chat/conversations/{id}
  API-->>Admin: Conversation resource
  Admin->>API: GET .../messages (cursor page)
  API-->>Admin: Message page
  Admin->>API: POST /api/broadcasting/auth
  API-->>Admin: channel auth OK
  Admin->>Pusher: subscribe private-conversation.{id}
  Admin->>API: POST .../messages { body, client_message_id }
  API->>API: persist message (sender = admin user)
  API->>Pusher: broadcast .MessageSent
  Pusher-->>Admin: .MessageSent
  API->>API: notify recipients immediately (FCM only; no chat DB row)
  API->>Pusher: .UnreadCountUpdated (recipient channels)
  API-->>Mobile: FCM attempted in-request when device tokens exist
```

### Recommended hooks

```ts
// Pseudocode — adapt to your state library
export function useAdminInboxChannel(echo: Echo, adminId: number, onEvent: (e: unknown) => void) {
  useEffect(() => {
    const channel = echo.private(`user.user.${adminId}`);
    channel.listen('.UnreadCountUpdated', onEvent);
    return () => {
      echo.leave(`user.user.${adminId}`);
    };
  }, [echo, adminId, onEvent]);
}

export function useConversationChannel(echo: Echo, conversationId: number, handlers: {
  onMessage: (payload: unknown) => void;
}) {
  useEffect(() => {
    const channel = echo.private(`conversation.${conversationId}`);
    channel.listen('.MessageSent', handlers.onMessage);
    // Optional future-proofing — currently not emitted:
    // channel.listen('.MessageRead', ...)
    // channel.listen('.ConversationUpdated', ...)
    return () => {
      echo.leave(`conversation.${conversationId}`);
    };
  }, [echo, conversationId, handlers]);
}
```

Rules:

- Authorize/subscribe only after the conversation is returned by the admin API.
- Unsubscribe when leaving the page.
- Guard against double `listen` in React Strict Mode by leaving/rejoining carefully.
- After reconnect, refetch messages and dedupe by `id` / `client_message_id`.

---

## 13. Sending Messages

### Confirmed backend behavior

- Sender always from Sanctum token.
- `client_message_id` provides idempotency.
- Closed / read-only conversations reject send (`403` via policy).
- Only text messages are created (`type: text`).

### Recommended send flow

1. Generate UUID `client_message_id`.
2. Insert optimistic message (`pending`).
3. `POST` message.
4. Replace optimistic row with server message (`sent`).
5. If `.MessageSent` arrives for same `id` or `client_message_id`, ignore duplicate.
6. On failure, mark `failed` and allow retry with the **same** `client_message_id`.

Handle:

| Status | UI behavior |
|---|---|
| `422` | Show field errors (`body` max/blank) |
| `403` | Disable composer; show read-only/closed state |
| `404` | Conversation missing — return to inbox |
| `429` | Temporarily disable send; retry later |
| Network | Keep pending; retry safely |

`409` is not specifically returned by chat controllers today.

---

## 14. Message Pagination

### Confirmed

- Cursor pagination, 50 per page, newest-first.
- Next page: pass `next_cursor` as `cursor` query param.
- End of history: `has_more === false` or `next_cursor === null`.

### Recommended frontend behavior

1. Initial fetch = latest page.
2. Render list reversed for chat UI (oldest at top).
3. “Load older” uses `next_cursor` (because ordering is desc, “next” moves toward older messages).
4. Preserve scroll anchor when prepending older messages.
5. Merge Pusher messages with REST results; dedupe by server `id`, then `client_message_id`.

There is **no** confirmed endpoint to fetch “messages after id X” for reconnect catch-up — refetch latest page and merge.

---

## 15. Read Receipts and Unread Counts

### Confirmed

- Mark-read endpoint updates participant `last_read_message_id` / `last_read_at`.
- `.MessageRead` is **not** broadcast today.
- `.UnreadCountUpdated` is broadcast to message **recipients**, not reliably useful for admin badge math.

### Recommended admin behavior

- Treat notification inbox unread as the primary admin badge.
- Optionally call mark-read when viewing a support thread (harmless no-op without participant row).
- Debounce mark-read; do not call once per bubble.
- On tab hidden, do not mark read.

---

## 16. Notifications in the Admin Dashboard

### Confirmed backend behavior

- Chat messages **do not** create rows in the Laravel `notifications` table.
- Chat push uses FCM only (`type: chat_message`) via synchronous `MessageNotificationService`.
- When a non-admin writes in support, **all admins** receive FCM fan-out (no DB inbox row for chat).
- When an admin writes in support, the customer/provider participant is notified (not other admins).
- Live chat unread event: `.UnreadCountUpdated` on `private-user.user.{adminId}`.
- FCM navigation fields: `conversation_id`, `message_id`, `service_request_id` (when applicable), `route: "chat"`.

### Recommended frontend behavior

- Maintain a notifications panel from REST + live upserts by notification `id`.
- On tap, navigate to `/admin/chat/conversations/{conversation_id}` only after confirming the conversation loads via API.
- Mark read when opened.
- Browser Notification API is optional and separate from Firebase mobile push.

---

## 17. Reconnection and Synchronization

1. Listen for Pusher connection state changes.
2. On reconnect:
   - refetch inbox first page
   - refetch open conversation latest messages
   - refetch notifications page
3. Dedupe by entity ids.
4. Retry failed outgoing messages with same `client_message_id`.
5. If private-channel auth returns `401`/`403`, force re-login.

---

## 18. Error Handling

| Status | Typical cause | Recommended UI |
|---|---|---|
| `401` | Missing/invalid Sanctum token | Logout, clear Echo |
| `403` | Not admin / cannot view or send | Toast + disable action; remove inaccessible conversation |
| `404` | Missing conversation/notification | Refresh list |
| `422` | Validation | Inline field errors |
| `429` | Chat send/read/open throttle | Backoff, disable send briefly |
| `500` | Server error | Retry |
| `503` | Seen for bulk notifications when Firebase missing | Show configuration error for that feature |

---

## 19. Security Checklist

- [ ] Never ship `PUSHER_APP_SECRET` or Firebase service-account JSON to React
- [ ] Never trust/send frontend `sender_id` / `sender_type`
- [ ] Subscribe only to conversations returned by admin API
- [ ] Clear Echo subscriptions and disconnect on logout
- [ ] Render message `body` as text (no unsanitized HTML)
- [ ] Protect stored tokens; never log Bearer tokens
- [ ] Validate notification navigation ids by re-fetching the conversation
- [ ] Do not expose unrelated admin profile fields beyond login payload needs
- [ ] Use HTTPS in production

---

## 20. Testing Checklist

- [ ] Admin login returns token + role admin
- [ ] Private channel auth succeeds for `conversation.{id}` and `user.user.{adminId}`
- [ ] Customer support thread list/send/close/reopen
- [ ] Provider support thread list/send/close/reopen
- [ ] Request conversation inspection (read-only composer)
- [ ] Message send/receive over Pusher
- [ ] Reconnect refresh without duplicates
- [ ] Unread/notification badge behavior
- [ ] Unauthorized subscription fails
- [ ] Rate-limit `429` on rapid sends
- [ ] Duplicate `client_message_id` does not create two rows
- [ ] Notification navigation opens correct conversation

---

## 21. React Implementation Checklist

1. Configure `VITE_*` env vars (no secrets).
2. Implement admin login + Axios bearer client.
3. Build Echo factory with Sanctum auth headers.
4. Implement admin chat API module.
5. Build inbox page with filters + cursor paging.
6. Subscribe to `user.user.{adminId}` for live notifications.
7. Build conversation pane + message cursor paging.
8. Subscribe to `conversation.{id}` for `.MessageSent`.
9. Implement optimistic send + `client_message_id`.
10. Wire close/reopen for support.
11. Wire notification inbox APIs + deep links.
12. Add reconnect refetch + logout cleanup.
13. Run the testing checklist.

---

## 22. Known Limitations

Confirmed from current Laravel code:

| Limitation | Detail |
|---|---|
| No attachments | Messages always created as `type: text` |
| No typing indicators | Not implemented |
| No presence channels | Not implemented |
| No message editing/delete API routes | Model has soft deletes / `edited_at`, but no chat HTTP delete/edit endpoints exposed |
| No admin assignment | Any admin can access support; no assignee field |
| No `ConversationCreated` / `ConversationClosed` events | Classes do not exist |
| `.MessageRead` not emitted | Mark-read updates DB only |
| `.ConversationUpdated` not emitted | Close/reopen are REST-only today |
| Admin unread counters weak | Admins are not support participants, so participant unread math stays near zero |
| Actor list `search` ignored | Non-admin `listForActor` accepts `search` from controller but does not apply it (admin list search works) |
| No CORS config file | `config/cors.php` is not present in this repo; rely on framework defaults / deployment config |
| Synchronous fan-out latency | Send-message waits for Pusher + FCM attempts; failures are logged and do not roll back the stored message |

---

## Security reminder

- `PUSHER_APP_SECRET` belongs only on the Laravel server.
- React must never contain the Pusher secret.
- Firebase service-account credentials belong only on the Laravel server.
- Private-channel authentication must send the Sanctum Bearer token.
- Frontends must never send `sender_id` or `sender_type`.
- The backend determines the sender from the authenticated token.

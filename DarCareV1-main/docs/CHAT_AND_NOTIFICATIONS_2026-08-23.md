# Chat and Notifications

Date: 2026-08-23
Last updated: 2026-08-23

## Architecture overview

DarCare chat uses:

- **Sanctum** for API authentication (Customer `User`, Admin `User`, Provider)
- **Conversations + participants** for all chat types
- **Pusher Channels Cloud** for realtime (`BROADCAST_CONNECTION=pusher`)
- **Firebase Cloud Messaging** for push via `device_tokens`
- **Laravel database notifications** for persistent **non-chat** inbox history

> **Chat-message notifications are transient** — they are **not** persisted in the
> Laravel `notifications` table. Chat realtime delivery uses Pusher (`.MessageSent`);
> chat background push uses FCM; chat unread state uses `conversation_participants`
> read pointers. Non-chat notification history (service requests, admin announcements,
> ratings, etc.) continues to use the Laravel notifications table.

Chat broadcasts and Firebase delivery are
processed synchronously. No queue worker is required for these features.

Trade-off: the send-message (and related notification) HTTP request waits for
Pusher and Firebase delivery attempts. Slow external responses may increase API
latency. The message remains stored if Pusher or Firebase fails.

## Actor model

| Actor | Storage | Morph alias | Sanctum |
|-------|---------|-------------|---------|
| Customer | `users.role = user` | `user` | Yes |
| Admin | `users.role = admin` | `user` | Yes |
| Artisan | `providers` table | `provider` | Yes |

Canonical user class: `App\Modules\Users\Models\User`  
Compatibility alias: `App\Models\User` extends the canonical model.

## Conversation types

| Type | Participants | Notes |
|------|--------------|-------|
| `request` | Customer + assigned provider | One per `service_request_id` |
| `support_customer` | Customer + admins | Admins access by role |
| `support_provider` | Provider + admins | Admins access by role |

Statuses: `open`, `closed`, `read_only`.

Final request statuses (`rejected`, `completed`, `cancelled`) set the request conversation to `read_only`.

## Authorization rules

- Request chat: only owning customer and currently assigned provider
- After reassignment: previous provider loses API + channel access; history remains
- Support: owner + any authenticated admin
- Admin may inspect request chats only via `/api/v1/admin/chat/*`
- Sender is always `$request->user()` — never client-supplied sender fields
- Private channels only; authorization checks morph type + id

## Database schema (chat)

- `conversations` (`service_request_id` nullable; set for `request`, null for support)
- `conversation_participants` (polymorphic)
- `messages` (adds `conversation_id`, soft deletes; `service_request_id` nullable)
- `device_tokens` (polymorphic, unique token)

`messages.service_request_id` is required for request-chat history and null for
`support_customer` / `support_provider` messages. The portable migration
`2026_08_23_120000_make_messages_service_request_id_nullable` only changes
nullability; it keeps existing values, indexes, and the foreign key to
`service_requests`. Rollback is refused if any support message still has a null
id — those rows are never deleted or given a fake request id.

Future cleanup (documented migration placeholder): drop `messages.service_request_id` and legacy `fcm_token` columns after clients migrate.

## API endpoints

### Chat

| Method | Endpoint | Roles |
|--------|----------|-------|
| GET | `/api/v1/chat/conversations` | Auth |
| POST | `/api/v1/chat/conversations` | Auth |
| GET | `/api/v1/chat/conversations/{id}` | Participant / support admin |
| GET | `/api/v1/chat/conversations/{id}/messages` | Participant / support admin |
| POST | `/api/v1/chat/conversations/{id}/messages` | Participant / support admin |
| PATCH | `/api/v1/chat/conversations/{id}/read` | Participant |
| PATCH | `/api/v1/chat/conversations/{id}/mute` | Participant |
| GET | `/api/v1/chat/unread-count` | Auth |

### Admin chat

| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/admin/chat/conversations` |
| GET | `/api/v1/admin/chat/conversations/{id}` |
| GET | `/api/v1/admin/chat/conversations/{id}/messages` |
| POST | `/api/v1/admin/chat/conversations/{id}/messages` |
| POST | `/api/v1/admin/chat/conversations/{id}/close` |
| POST | `/api/v1/admin/chat/conversations/{id}/reopen` |

### Device tokens / notifications

| Method | Endpoint |
|--------|----------|
| POST | `/api/v1/device-tokens` |
| DELETE | `/api/v1/device-tokens` |
| GET | `/api/v1/notifications` |
| PATCH | `/api/v1/notifications/read-all` |
| PATCH | `/api/v1/notifications/{id}/read` |
| DELETE | `/api/v1/notifications/{id}` |

### Legacy (deprecated)

| Method | Endpoint | Meta |
|--------|----------|------|
| GET/POST | `/api/v1/requests/{id}/messages` | Returns `meta.deprecated` + `replacement_endpoint` |

## Request lifecycle behavior

1. Customer creates request → DB + FCM to provider
2. Provider updates status (`accepted|rejected|delayed|completed|cancelled`) → notify customer
3. Final statuses → conversation `read_only`
4. Admin reassign → old provider leaves, new provider joins

## Provider reassignment

`PATCH /api/v1/admin/service-requests/{id}/reassign` with `{ "provider_id": N }`

## Pusher setup

1. Create a Pusher Channels app
2. Set in `.env` (never commit secrets):

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_USE_TLS=true
```

3. Auth endpoint: `POST /api/broadcasting/auth` with Sanctum bearer token

## Private channel names

| Laravel channel | Client name |
|-----------------|-------------|
| `conversation.{id}` | `private-conversation.{id}` |
| `user.user.{id}` | `private-user.user.{id}` |
| `user.provider.{id}` | `private-user.provider.{id}` |

## Events

| Event | Channel | Name |
|-------|---------|------|
| `MessageSent` | conversation | `.MessageSent` |
| `MessageRead` | conversation | `.MessageRead` |
| `ConversationUpdated` | conversation | `.ConversationUpdated` |
| `NotificationCreated` | typed user channel | `.NotificationCreated` (non-chat database notifications only) |
| `UnreadCountUpdated` | typed user channel | `.UnreadCountUpdated` |

## Firebase setup

```env
FIREBASE_CREDENTIALS=storage/app/json/firebase_credentials.json
FIREBASE_PROJECT_ID=your-project-id
```

Do not commit the JSON credentials file. Place it outside VCS or gitignore it.

## Device-token lifecycle

1. App registers token after login
2. Token uniqueness transfers ownership if reused
3. Logout may send `device_token` to invalidate current device only
4. Invalid FCM tokens are invalidated during send

## Synchronous execution

Chat broadcasts and Firebase delivery are
processed synchronously. No queue worker is required for these features.

Recommended defensive fallback when the app has no other queued work:

```env
QUEUE_CONNECTION=sync
```

Do **not** rely on the sync queue driver as a substitute for chat jobs — chat and
notification modules do not dispatch jobs at all.

Trade-off: send-message waits for notification delivery attempts. Slow Pusher or
Firebase responses may increase API response time. The message remains stored if
an external notification service fails.

## Migration process

```bash
php artisan migrate
```

Existing `messages` rows are assigned to request conversations without deleting history.

Support messages may then store `service_request_id = NULL`. Request messages keep
their original service request id.

## Deployment checklist

1. Backup database
2. `php artisan migrate`
3. Configure Pusher + Firebase env vars
4. Set `QUEUE_CONNECTION=sync` unless another module needs a real queue
5. `php artisan config:clear`
6. Confirm `/api/broadcasting/auth` with Sanctum
7. `php artisan test`

Reverb is **not** required when using Pusher Cloud.
No `php artisan queue:work` process is required for chat or notifications.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| No realtime | `BROADCAST_CONNECTION`, Pusher credentials, Sanctum broadcast auth |
| No push | Firebase credentials path, `device_tokens`, application logs |
| Slow send-message | Pusher/Firebase latency (synchronous fan-out) |
| 403 on chat | Participant / reassignment / read_only status |
| Admin 403 | `auth:sanctum` + `role=admin` |

## Security considerations

- Admin routes require Sanctum + `EnsureAdmin`
- No public chat channels
- Morph-aware channel auth prevents User/Provider ID collisions
- Message body preview only in push payloads
- Rate limits: chat-send, chat-read, chat-open, device-tokens, admin-bulk-notifications

## Historical chat notification rows (optional cleanup)

New chat messages no longer insert into `notifications`. Existing rows with
`type = 'chat_message'` may remain as historical data and are not deleted
automatically.

Optional manual cleanup (do **not** run in a migration):

```sql
-- Review first:
SELECT COUNT(*) FROM notifications WHERE type = 'chat_message';

-- Optional delete after review:
-- DELETE FROM notifications WHERE type = 'chat_message';
```

## Known limitations (backend phase)

- No attachments / typing / presence
- No message edit UI workflow beyond soft-delete support fields
- No Flutter or admin SPA in this repository
- Legacy `fcm_token` columns retained temporarily

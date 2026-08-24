# Flutter Customer and Provider Chat Integration

Last updated: 2026-08-22

> Chat broadcasts and Firebase delivery are
> processed synchronously. No queue worker is required for these features.
>
> Trade-off: the send-message request waits for notification delivery attempts.
> Slow Pusher or Firebase responses may increase API response time.
> The message remains stored if an external notification service fails.

## 1. Purpose and Scope

This guide explains how to integrate DarCare’s Laravel chat and notification backend into:

- the **Customer** Flutter app (`User` with `role = user`)
- the **Provider / artisan** Flutter app (`Provider` model)

### Covered capabilities

| Capability | Confirmed |
|---|---|
| Request-bound customer ↔ provider chat (`request`) | Yes |
| Customer ↔ admin support (`support_customer`) | Yes |
| Provider ↔ admin support (`support_provider`) | Yes |
| Real-time messages via Pusher private channels | Yes |
| Background push via Firebase Cloud Messaging | Yes (synchronous from Laravel) |
| Persistent notification history via Laravel APIs | Yes — **non-chat** notifications only |
| Multiple device tokens per actor | Yes |
| Mute conversation push fan-out | Yes (`muted_at` skips recipient in notify job) |

### Document labels

| Label | Meaning |
|---|---|
| **Confirmed backend behavior** | Verified from Laravel source |
| **Recommended frontend implementation** | Suggested Flutter patterns |
| **Assumption requiring verification** | Flutter app repository was not present in this workspace |

---

## 2. Confirmed Backend Contract

### Base URL and auth

| Item | Value |
|---|---|
| API prefix | `/api/v1` |
| Example | `https://api.example.com/api/v1/...` |
| Auth | Laravel Sanctum Bearer token |
| Customer login | `POST /api/v1/auth/login/user` |
| Provider login | `POST /api/v1/auth/login/provider` |
| Logout | `POST /api/v1/auth/logout` (optional `device_token`) |
| Broadcast auth | `POST /api/broadcasting/auth` (`api` + `auth:sanctum`) |

Login success uses envelope `{ success, message, data, errors }` where `data` includes actor fields + `token` via `AuthResource` (id, name, email, phone, role, token).

### Conversation endpoints

| Method | Endpoint | Notes |
|---|---|---|
| `GET` | `/api/v1/chat/conversations` | Cursor list for current actor |
| `POST` | `/api/v1/chat/conversations` | Open/get conversation (`throttle:chat-open`) |
| `GET` | `/api/v1/chat/conversations/{id}` | Show one |
| `GET` | `/api/v1/chat/conversations/{id}/messages` | Cursor messages (50, newest first) |
| `POST` | `/api/v1/chat/conversations/{id}/messages` | Send (`throttle:chat-send`) |
| `PATCH` | `/api/v1/chat/conversations/{id}/read` | Mark read (`throttle:chat-read`) |
| `PATCH` | `/api/v1/chat/conversations/{id}/mute` | `{ "muted": true\|false }` |
| `GET` | `/api/v1/chat/unread-count` | Totals by type |

Open conversation body:

| Field | Rules |
|---|---|
| `type` | required: `request` \| `support_customer` \| `support_provider` |
| `service_request_id` | required if `type=request`; prohibited otherwise; must exist |

### Legacy endpoints (do not build new UI on these)

| Method | Endpoint | Meta |
|---|---|---|
| `GET`/`POST` | `/api/v1/requests/{requestId}/messages` | Returns `meta.deprecated` + `replacement_endpoint` |

### Device-token endpoints

| Method | Endpoint | Body |
|---|---|---|
| `POST` | `/api/v1/device-tokens` | `token`, `platform` (`android`\|`ios`\|`web`), optional `device_name`, `device_identifier` |
| `DELETE` | `/api/v1/device-tokens` | `token` |

Throttle: `device-tokens` = 20/min/actor.

### Notification endpoints

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/notifications` |
| `PATCH` | `/api/v1/notifications/read-all` |
| `PATCH` | `/api/v1/notifications/{id}/read` |
| `DELETE` | `/api/v1/notifications/{id}` |

Query: `unread`, `per_page` (default 15), `page`.

### Channel names

| Laravel auth name | Client subscribe name |
|---|---|
| `conversation.{id}` | `private-conversation.{id}` |
| `user.user.{userId}` | `private-user.user.{userId}` |
| `user.provider.{providerId}` | `private-user.provider.{providerId}` |

### Event names and payloads

| Event | Channel | Dispatched today? |
|---|---|---|
| `.MessageSent` | conversation | **Yes** |
| `.MessageRead` | conversation | **No** (class exists only) |
| `.ConversationUpdated` | conversation | **No** (class exists only) |
| `.NotificationCreated` | typed user channel | **No** for chat (non-chat database notifications only) |
| `.UnreadCountUpdated` | typed user channel | **Yes** |

#### `.MessageSent`

```json
{
  "id": 101,
  "client_message_id": "client-uuid",
  "conversation_id": 12,
  "sender": { "type": "user", "id": 9, "display_role": "customer" },
  "type": "text",
  "body": "Hello",
  "reply_to": null,
  "created_at": "2026-07-21T12:00:00+00:00"
}
```

#### `.NotificationCreated`

```json
{
  "notification": {
    "id": "uuid",
    "type": "chat_message",
    "title": "New message",
    "body": "preview...",
    "data": {},
    "route": "chat",
    "conversation_id": 12,
    "message_id": 101,
    "service_request_id": 55,
    "read_at": null,
    "is_read": false,
    "created_at": "..."
  }
}
```

#### `.UnreadCountUpdated`

```json
{
  "conversation_id": 12,
  "unread": {
    "total": 2,
    "by_type": {
      "request": 2,
      "support_customer": 0,
      "support_provider": 0
    }
  },
  "conversation_unread": 2
}
```

Chat FCM data fields (from synchronous `MessageNotificationService`):

| Key | Example |
|---|---|
| `title` | `New message` |
| `body` | truncated preview (≤80 chars) |
| `type` | `chat_message` |
| `conversation_id` | int |
| `message_id` | int |
| `service_request_id` | int or null |
| `route` | `chat` |

FCM also merges stringified data and sets `click_action = FLUTTER_NOTIFICATION_CLICK`.

### Pagination behavior

| Resource | Style | Size |
|---|---|---|
| Conversations | Cursor (`cursor`, `next_cursor`, `has_more`; actor list also `prev_cursor`) | default 15, max 50 |
| Messages | Cursor, newest first | 50 |
| Notifications | Page (`page`, `per_page`) | default 15 |

### Error response format

Standard trait:

```json
{
  "success": false,
  "message": "Error text",
  "data": null,
  "errors": null
}
```

`429`:

```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "data": null,
  "errors": null
}
```

`422` validation uses Laravel’s JSON validation shape (`message` + `errors`).

### Conversation / message resource fields (confirmed)

**Conversation:** `id`, `type`, `status`, optional `service_request`, `participants`, optional `last_message`, `last_message_at`, `unread_count`, `muted`, `closed_at`, `created_at`

**Participant:** `type`, `id`, `display_role`, `name`, `joined_at`, `left_at`, `muted`, `last_read_at`

**Message:** `id`, `conversation_id`, `service_request_id`, `client_message_id`, `type`, `body`, `deleted`, `sender`, `reply_to_message_id`, `created_at`

Statuses: `open`, `closed`, `read_only`  
Types: `request`, `support_customer`, `support_provider`

---

## 3. Customer and Provider Differences

Confirmed against policies, controllers, channel auth, and reassignment tests:

| Capability | Customer | Provider |
|---|---|---|
| Open request conversation | Own request only | Currently assigned request only |
| Open `support_customer` | Yes | No (`403`) |
| Open `support_provider` | No (`403`) | Yes |
| Typed user channel | `private-user.user.{userId}` | `private-user.provider.{providerId}` |
| Receive request-message FCM | From assigned provider (and not self) | From request customer (and not self) |
| Access after provider reassignment | Owner retains access | Old provider loses access (`403` on messages); new provider gains access + history |
| Morph alias | `user` | `provider` |
| Display role for self messages | `customer` | `artisan` |
| Support display for admin messages | `support` | `support` |

Morph map enforced as `user` / `provider` — never compare actors by numeric id alone across types.

---

## 4. Required Flutter Packages

### Assumption requiring verification

No Flutter `pubspec.yaml` exists in this Laravel repository. Treat the following as **recommended examples**, and skip any package already covered by an equivalent in the real apps.

```yaml
dependencies:
  dio: # HTTP client
  flutter_secure_storage: # Sanctum token storage
  pusher_channels_flutter: # Pusher private channels
  firebase_core: # Firebase bootstrap
  firebase_messaging: # FCM
  flutter_local_notifications: # Foreground local notifications
  uuid: # client_message_id
  connectivity_plus: # reconnect triggers
```

| Package | Why needed |
|---|---|
| `dio` | REST + broadcast-auth HTTP calls |
| `flutter_secure_storage` | Store Sanctum token off plain prefs |
| `pusher_channels_flutter` | Subscribe to private channels with auth callback |
| `firebase_core` / `firebase_messaging` | Background/terminated push |
| `flutter_local_notifications` | Show alerts while app is foregrounded |
| `uuid` | Idempotent `client_message_id` |
| `connectivity_plus` | Refetch/resubscribe when network returns |

Version numbers were **not** verified against an app lockfile — pin versions in the real projects.

---

## 5. Flutter Environment Configuration

Safe client configuration only:

```bash
flutter run \
  --dart-define=API_BASE_URL=https://api.example.com \
  --dart-define=PUSHER_APP_KEY=YOUR_PUSHER_APP_KEY \
  --dart-define=PUSHER_CLUSTER=YOUR_PUSHER_CLUSTER \
  --dart-define=PUSHER_AUTH_ENDPOINT=https://api.example.com/api/broadcasting/auth
```

Example config class:

```dart
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://api.example.com',
  );
  static const pusherKey = String.fromEnvironment('PUSHER_APP_KEY');
  static const pusherCluster = String.fromEnvironment('PUSHER_CLUSTER');
  static const pusherAuthEndpoint = String.fromEnvironment(
    'PUSHER_AUTH_ENDPOINT',
    defaultValue: 'https://api.example.com/api/broadcasting/auth',
  );

  static String get apiV1 => '$apiBaseUrl/api/v1';
}
```

### Allowed on device

- Pusher **app key** + cluster
- Firebase **client** config (`google-services.json` / `GoogleService-Info.plist`)

### Never ship on device

- `PUSHER_APP_SECRET`
- Firebase service-account JSON / private key
- Laravel `APP_KEY`
- Database credentials

---

## 6. Recommended Flutter Architecture

**Assumption requiring verification:** no confirmed Bloc/Riverpod/GetX usage. Feature-first structure:

```text
lib/
  core/
    api/
    auth/
    realtime/
    notifications/
    storage/
  features/chat/
    data/
    domain/
    presentation/
  features/notifications/
```

| Component | Responsibility |
|---|---|
| `ChatApiClient` | REST calls + DTO parsing |
| `ChatRepository` | Merge REST + realtime + local pending sends |
| `PusherService` | Connect, authorize private channels, bind events |
| `FirebaseMessagingService` | Permissions, token, handlers |
| `DeviceTokenService` | Register/refresh/remove via API |
| `ChatController` / Cubit / Notifier | UI state machine |
| `NotificationRouter` | Map notification payload → screens |

Use whichever state library the apps already use.

---

## 7. Authentication and Token Storage

### Confirmed backend behavior

- Customer/provider login returns Sanctum plain-text token.
- Logout deletes current access token.
- Optional logout body `device_token` **invalidates** that FCM token (`invalidated_at`) without deleting other devices.
- Dedicated `DELETE /device-tokens` **deletes** the token row for the current actor.

### Recommended frontend flow

1. Store Sanctum token in `flutter_secure_storage`.
2. Attach Bearer token on Dio and Pusher auth.
3. On re-login, replace token and reconnect Pusher.
4. On logout:
   - `DELETE /device-tokens` with current FCM token (preferred for full removal), and/or pass `device_token` to logout
   - call logout API
   - disconnect Pusher
   - clear secure storage + in-memory chat caches

Do not decode untrusted JWT-like assumptions — Sanctum tokens are opaque API tokens. Read actor `id` from the login/profile response.

---

## 8. API Client Setup

```dart
import 'package:dio/dio.dart';

class ApiClient {
  ApiClient({required this.getToken})
      : dio = Dio(
          BaseOptions(
            baseUrl: '${AppConfig.apiV1}/',
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 20),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          // Do not log Authorization header or FCM tokens.
          handler.next(error);
        },
      ),
    );
  }

  final Future<String?> Function() getToken;
  final Dio dio;
}
```

Handle `401` (reauth), `429` (backoff), and timeouts without dropping optimistic outgoing messages.

---

## 9. Pusher Client Setup

Use the app’s existing Pusher package if present. Example with `pusher_channels_flutter`:

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class PusherService {
  PusherService({required this.getToken});

  final Future<String?> Function() getToken;
  final PusherChannelsFlutter _pusher = PusherChannelsFlutter.getInstance();

  Future<void> connect() async {
    await _pusher.init(
      apiKey: AppConfig.pusherKey,
      cluster: AppConfig.pusherCluster,
      onAuthorizer: (channelName, socketId, options) async {
        final token = await getToken();
        final dio = Dio();
        final response = await dio.post(
          AppConfig.pusherAuthEndpoint,
          data: {
            'channel_name': channelName,
            'socket_id': socketId,
          },
          options: Options(
            headers: {
              'Authorization': 'Bearer $token',
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        );
        return response.data;
      },
      onConnectionStateChange: (current, previous) {
        // Recommended: trigger resubscribe + refetch when connected
      },
      onError: (message, code, exception) {
        // Log without tokens
      },
    );
    await _pusher.connect();
  }

  Future<void> subscribeConversation(
    int conversationId, {
    required void Function(dynamic data) onMessageSent,
  }) async {
    // Native clients use the private- prefix.
    final channelName = 'private-conversation.$conversationId';
    await _pusher.subscribe(
      channelName: channelName,
      onEvent: (event) {
        if (event.eventName == 'MessageSent' ||
            event.eventName == '.MessageSent') {
          onMessageSent(event.data);
        }
      },
    );
  }

  Future<void> disconnect() async {
    await _pusher.disconnect();
  }
}
```

### Naming difference (confirmed)

- Laravel registers: `conversation.{id}`
- Pusher private client name: `private-conversation.{id}`
- Authorization endpoint receives the **client** channel name (`private-...`)

Package event name formatting can include or omit the leading dot — bind defensively for both `MessageSent` and `.MessageSent`.

---

## 10. Typed User Channels

| App | Channel |
|---|---|
| Customer | `private-user.user.{userId}` |
| Provider | `private-user.provider.{providerId}` |

Used for:

- `.NotificationCreated` — inbox upsert
- `.UnreadCountUpdated` — badge + conversation unread
- Indirect inbox freshness (no dedicated conversation-created event)

Obtain `{userId}` / `{providerId}` from authenticated profile/login payload — do not invent ids.

---

## 11. Conversation Channels

When opening a chat screen:

1. Confirm conversation via API (`GET` or `POST` open).
2. Subscribe `private-conversation.{id}`.
3. Bind `.MessageSent` (and optionally unused `.MessageRead` / `.ConversationUpdated` for forward compatibility).
4. Fetch latest message page.
5. Dedupe REST + events by `id` and `client_message_id`.
6. Mark read when visible.
7. Unsubscribe on dispose.
8. After reconnect, refetch latest page and merge.

```mermaid
sequenceDiagram
  participant Customer as Customer Flutter
  participant API as Laravel API
  participant DB as Database
  participant Pusher as Pusher
  participant Provider as Provider Flutter

  Customer->>API: POST /api/v1/chat/conversations/{id}/messages
  API->>DB: transaction create message + update conversation
  API->>Pusher: broadcast .MessageSent (ShouldBroadcastNow)
  Pusher-->>Provider: private-conversation.{id} .MessageSent
  API->>API: send FCM immediately (no chat DB notification)
  API->>Pusher: .UnreadCountUpdated on provider channel
  API-->>Provider: FCM (if tokens present; same HTTP request)
  Provider->>Provider: notification tap → open conversation
  Provider->>API: GET conversation + messages
```

---

## 12. Data Models

Null-safe Dart models based on API resources:

```dart
class ChatMessageModel {
  ChatMessageModel({
    required this.id,
    required this.conversationId,
    required this.serviceRequestId,
    required this.clientMessageId,
    required this.type,
    required this.body,
    required this.deleted,
    required this.sender,
    required this.replyToMessageId,
    required this.createdAt,
  });

  final int id;
  final int conversationId;
  final int? serviceRequestId;
  final String? clientMessageId;
  final String type;
  final String? body;
  final bool deleted;
  final MessageSenderModel sender;
  final int? replyToMessageId;
  final DateTime? createdAt;

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    return ChatMessageModel(
      id: json['id'] as int,
      conversationId: json['conversation_id'] as int,
      serviceRequestId: json['service_request_id'] as int?,
      clientMessageId: json['client_message_id'] as String?,
      type: json['type'] as String? ?? 'text',
      body: json['body'] as String?,
      deleted: json['deleted'] as bool? ?? false,
      sender: MessageSenderModel.fromJson(
        Map<String, dynamic>.from(json['sender'] as Map),
      ),
      replyToMessageId: json['reply_to_message_id'] as int?,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : null,
    );
  }
}

class MessageSenderModel {
  MessageSenderModel({
    required this.type,
    required this.id,
    required this.displayRole,
  });

  final String type; // user | provider
  final int id;
  final String displayRole; // customer | artisan | support

  factory MessageSenderModel.fromJson(Map<String, dynamic> json) {
    return MessageSenderModel(
      type: json['type'] as String,
      id: json['id'] as int,
      displayRole: json['display_role'] as String,
    );
  }
}

class ConversationModel {
  ConversationModel({
    required this.id,
    required this.type,
    required this.status,
    required this.participants,
    required this.lastMessageAt,
    required this.unreadCount,
    required this.muted,
    required this.closedAt,
    required this.createdAt,
    this.serviceRequest,
    this.lastMessage,
  });

  final int id;
  final String type;
  final String status;
  final Map<String, dynamic>? serviceRequest;
  final List<ConversationParticipantModel> participants;
  final ChatMessageModel? lastMessage;
  final DateTime? lastMessageAt;
  final int unreadCount;
  final bool muted;
  final DateTime? closedAt;
  final DateTime? createdAt;

  bool get isReadOnly => status == 'read_only' || status == 'closed';

  factory ConversationModel.fromJson(Map<String, dynamic> json) {
    return ConversationModel(
      id: json['id'] as int,
      type: json['type'] as String,
      status: json['status'] as String,
      serviceRequest: json['service_request'] == null
          ? null
          : Map<String, dynamic>.from(json['service_request'] as Map),
      participants: (json['participants'] as List? ?? [])
          .map((e) => ConversationParticipantModel.fromJson(
                Map<String, dynamic>.from(e as Map),
              ))
          .toList(),
      lastMessage: json['last_message'] == null
          ? null
          : ChatMessageModel.fromJson(
              Map<String, dynamic>.from(json['last_message'] as Map),
            ),
      lastMessageAt: json['last_message_at'] != null
          ? DateTime.parse(json['last_message_at'] as String)
          : null,
      unreadCount: json['unread_count'] as int? ?? 0,
      muted: json['muted'] as bool? ?? false,
      closedAt: json['closed_at'] != null
          ? DateTime.parse(json['closed_at'] as String)
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : null,
    );
  }
}

class ConversationParticipantModel {
  ConversationParticipantModel({
    required this.type,
    required this.id,
    required this.displayRole,
    required this.name,
    required this.joinedAt,
    required this.leftAt,
    required this.muted,
    required this.lastReadAt,
  });

  final String type;
  final int id;
  final String displayRole;
  final String? name;
  final DateTime? joinedAt;
  final DateTime? leftAt;
  final bool muted;
  final DateTime? lastReadAt;

  factory ConversationParticipantModel.fromJson(Map<String, dynamic> json) {
    return ConversationParticipantModel(
      type: json['type'] as String,
      id: json['id'] as int,
      displayRole: json['display_role'] as String,
      name: json['name'] as String?,
      joinedAt: json['joined_at'] != null
          ? DateTime.parse(json['joined_at'] as String)
          : null,
      leftAt: json['left_at'] != null
          ? DateTime.parse(json['left_at'] as String)
          : null,
      muted: json['muted'] as bool? ?? false,
      lastReadAt: json['last_read_at'] != null
          ? DateTime.parse(json['last_read_at'] as String)
          : null,
    );
  }
}

class UnreadCountModel {
  UnreadCountModel({required this.total, required this.byType});

  final int total;
  final Map<String, int> byType;

  factory UnreadCountModel.fromJson(Map<String, dynamic> json) {
    final raw = Map<String, dynamic>.from(json['by_type'] as Map? ?? {});
    return UnreadCountModel(
      total: json['total'] as int? ?? 0,
      byType: raw.map((k, v) => MapEntry(k, v as int)),
    );
  }
}

class NotificationModel {
  NotificationModel({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.data,
    required this.route,
    required this.conversationId,
    required this.messageId,
    required this.serviceRequestId,
    required this.readAt,
    required this.isRead,
    required this.createdAt,
  });

  final String id;
  final String type;
  final String? title;
  final String? body;
  final Map<String, dynamic> data;
  final String? route;
  final int? conversationId;
  final int? messageId;
  final int? serviceRequestId;
  final DateTime? readAt;
  final bool isRead;
  final DateTime? createdAt;

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'].toString(),
      type: json['type']?.toString() ?? '',
      title: json['title'] as String?,
      body: json['body'] as String?,
      data: Map<String, dynamic>.from(json['data'] as Map? ?? {}),
      route: json['route'] as String?,
      conversationId: json['conversation_id'] as int?,
      messageId: json['message_id'] as int?,
      serviceRequestId: json['service_request_id'] as int?,
      readAt: json['read_at'] != null
          ? DateTime.tryParse(json['read_at'].toString())
          : null,
      isRead: json['is_read'] as bool? ?? false,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }
}

class CursorPaginationModel<T> {
  CursorPaginationModel({
    required this.data,
    required this.nextCursor,
    required this.hasMore,
    this.prevCursor,
  });

  final List<T> data;
  final String? nextCursor;
  final String? prevCursor;
  final bool hasMore;
}
```

Handle deleted messages (`deleted: true`, `body: null`), nullable ISO-8601 dates, and polymorphic sender types.

---

## 13. Customer Chat Flows

### Request chat

1. Customer opens one of their service requests.
2. `POST /chat/conversations` with `{ "type": "request", "service_request_id": <id> }` (idempotent open).
3. Fetch messages; subscribe to conversation channel.
4. Send messages with `client_message_id`.
5. Provider receives Pusher while online, else FCM push (no chat DB notification row).
6. If request reaches final status (`rejected`, `completed`, `cancelled`), conversation becomes `read_only` — UI stays readable, composer disabled.

### Customer support

1. `POST /chat/conversations` with `{ "type": "support_customer" }`.
2. Show admin senders as **Support Team** (`display_role == support`).
3. Notification taps route with `conversation_id` / `route: chat`.

---

## 14. Provider Chat Flows

### Assigned request chat

1. Provider opens an assigned service request.
2. Open/retrieve the request conversation.
3. Subscribe + send like the customer flow.
4. Customer receives Pusher/FCM.

### Provider support

1. `POST /chat/conversations` with `{ "type": "support_provider" }`.
2. Chat with admin support team.

### Reassignment (confirmed)

- Admin reassigns via backend service-request reassignment API.
- Old provider participant gets `left_at` set.
- Old provider API/channel access fails authorization (`403` on messages in tests).
- Frontend must: remove conversation locally, unsubscribe, refresh assigned lists.
- New provider should refresh conversations and may open the same conversation id with prior history.

---

## 15. Listing Conversations

1. `GET /chat/conversations` (`type`, `status`, optional `cursor`).
2. Render `last_message`, `unread_count`, `status`.
3. Keep local order by `last_message_at` desc.
4. Subscribe typed actor channel; on `.UnreadCountUpdated` / `.NotificationCreated`, upsert and reorder.
5. Pull-to-refresh refetches first page.
6. Handle empty/loading/error independently.

**Note:** actor list `search` is accepted by the controller but **not applied** in `listForActor` today — do not rely on server-side search for mobile inbox until backend implements it. Admin search works on admin endpoints only.

---

## 16. Loading Messages

1. Initial request returns newest 50.
2. Reverse for UI (oldest top / newest bottom — match your list widget).
3. Load older with `cursor = next_cursor` while `has_more`.
4. Preserve scroll offset when prepending.
5. Merge Pusher events; dedupe by server `id` then `client_message_id`.
6. Show date separators locally.
7. Render deleted placeholders when `deleted == true`.
8. Disable composer when `status` is `closed` or `read_only`.

---

## 17. Sending Messages

### Confirmed backend behavior

- Body required, trimmed, max 5000.
- Optional `reply_to_message_id` must exist in same conversation (service validates).
- Optional `client_message_id` max 100; duplicate returns existing message.
- Never send sender fields.
- Rate limit 30 sends/min/actor.

### Recommended Dart flow

```dart
Future<void> sendMessage({
  required int conversationId,
  required String body,
  required ChatRepository repo,
}) async {
  final clientId = const Uuid().v4();
  final pending = repo.addOptimistic(
    conversationId: conversationId,
    body: body,
    clientMessageId: clientId,
  );

  try {
    final saved = await repo.api.sendMessage(
      conversationId: conversationId,
      body: body,
      clientMessageId: clientId,
    );
    repo.replaceOptimistic(pending, saved);
  } on DioException catch (e) {
    repo.markFailed(pending, e);
    // Retry later with the same clientMessageId.
  }
}
```

When the sender’s own `.MessageSent` echo arrives, ignore if `id` or `client_message_id` already present.

Block send when conversation is not `open`.

---

## 18. Read Receipts

### Confirmed

- `PATCH /chat/conversations/{id}/read` with optional `{ "last_message_id": <id> }`.
- If omitted, backend uses conversation `last_message_id`.
- Ignores regressive ids (older than current last read).
- `.MessageRead` is **not** broadcast currently — do not depend on live read receipts.

### Recommended

- Call mark-read once when conversation becomes visible and latest message id is known.
- Debounce; never one request per bubble.
- Skip while `AppLifecycleState` is paused/inactive or screen not visible.
- Update local unread using `.UnreadCountUpdated` and/or refetch `/chat/unread-count`.

---

## 19. Device Token Registration

### Confirmed lifecycle hooks on backend

1. `POST /device-tokens` registers/transfers token ownership if reused.
2. Invalid FCM tokens are invalidated during send.
3. Logout can invalidate one token; DELETE removes current actor’s token row.

### Recommended Flutter service

```dart
class DeviceTokenService {
  DeviceTokenService(this.api, this.messaging);

  final ApiClient api;
  final FirebaseMessaging messaging;
  String? _currentToken;

  Future<void> start({required String platform}) async {
    await messaging.requestPermission();
    final token = await messaging.getToken();
    if (token != null) {
      await register(token, platform);
    }
    messaging.onTokenRefresh.listen((t) => register(t, platform));
  }

  Future<void> register(String token, String platform) async {
    _currentToken = token;
    await api.dio.post(
      'device-tokens',
      data: {
        'token': token,
        'platform': platform, // android | ios
        'device_name': null,
        'device_identifier': null,
      },
    );
  }

  Future<void> removeCurrent() async {
    final token = _currentToken ?? await messaging.getToken();
    if (token == null) return;
    await api.dio.delete('device-tokens', data: {'token': token});
    _currentToken = null;
  }
}
```

Never log full FCM tokens.

---

## 20. Firebase Messaging States

### Foreground

Use `FirebaseMessaging.onMessage`.

Recommended:

- If the open conversation id matches payload `conversation_id`, update chat UI only (skip local notification).
- Otherwise show in-app banner and/or local notification.
- Always upsert notification inbox from payload/DB refresh.

### Background

Register a background handler (platform requirements apply). Persist navigation data for later routing.

### Terminated

```dart
final initial = await FirebaseMessaging.instance.getInitialMessage();
```

### Notification tap

```dart
FirebaseMessaging.onMessageOpenedApp.listen(handleMessage);
```

Route using confirmed keys:

- `type`
- `conversation_id`
- `message_id`
- `service_request_id`
- `route`

Re-fetch conversation via API before showing — do not trust push ids alone for authorization.

Sender does **not** receive self FCM for their own chat messages (confirmed by notify recipient filtering + tests).

---

## 21. Local Notifications

Use `flutter_local_notifications` when you need foreground system notifications.

Recommended:

- Create an Android channel such as `chat_messages` (default importance, normal sound — not alarm/emergency).
- Request iOS permissions via Firebase + local notifications plugins.
- Deduplicate notification ids (for example use `message_id`).
- Do not configure Firebase service-account credentials inside Flutter.

---

## 22. Notification Inbox

1. `GET /notifications` (optionally `unread=true`).
2. Show unread indicator from list / counts.
3. `PATCH /{id}/read`, `PATCH /read-all`, `DELETE /{id}`.
4. Navigate with `conversation_id` / `service_request_id`.
5. Upsert from `.NotificationCreated` by notification `id` to avoid duplicates.

Pagination is page-based, not cursor-based.

---

## 23. App Lifecycle and Connectivity

Handle `resumed` / `inactive` / `paused` / `detached`:

| Event | Recommended action |
|---|---|
| resumed | ensure Pusher connected; resubscribe actor + open conversation; refetch unread + open messages |
| paused/inactive | stop mark-read; optionally keep socket |
| connectivity restored | reconnect + refetch lists |
| detached/logout | disconnect and clear |

Avoid duplicate subscriptions by tracking joined channel names.

---

## 24. State Management

Architecture-neutral states for chat screens:

| State | Meaning |
|---|---|
| `initial` | Not loaded |
| `loading` | First fetch |
| `loaded` | Data ready |
| `loadingMore` | Older messages / next cursor |
| `sending` | Outgoing in flight |
| `reconnecting` | Socket/network recovery |
| `failure` | Recoverable error |
| `unauthorized` | `401` / hard auth failure |
| `readOnly` | Conversation not `open` |

Wire these into the app’s existing Bloc/Cubit/Riverpod/Provider/GetX layer — do not introduce a new global pattern solely for chat.

---

## 25. Error Handling

| Condition | Frontend behavior |
|---|---|
| `401` | Logout / reauthenticate; disconnect Pusher |
| `403` | Remove inaccessible conversation (common after reassignment); disable send |
| `404` | Refresh conversation/notification lists |
| `422` | Show validation under composer |
| `429` | Temporarily disable send; exponential backoff |
| `500` / `503` | Retry with message; FCM may be unavailable server-side |
| Network unavailable | Keep pending optimistic message; retry with same `client_message_id` |
| Pusher auth failure | Reauth token; if still failing, show realtime-degraded mode |
| Firebase token failure | Allow chat REST/Pusher without push; retry registration later |

`409` is not a dedicated chat contract status today.

---

## 26. Security Checklist

- [ ] Store Sanctum token in secure storage
- [ ] Never store Pusher secret or Firebase service-account JSON
- [ ] Never send `sender_id` / `sender_type`
- [ ] Render message text safely (no raw HTML injection)
- [ ] Mask tokens in logs
- [ ] Unsubscribe + disconnect on logout
- [ ] Remove/invalidate current device token on logout
- [ ] Re-authorize conversation via API before honoring notification deep links
- [ ] HTTPS only in production
- [ ] Do not let users pick arbitrary participants — only open via typed APIs

---

## 27. Android Configuration

### Assumption requiring verification

Exact Gradle files live in the Flutter apps (not this repo). Typical requirements for current FlutterFire:

1. Place `google-services.json` in `android/app/`.
2. Apply Google Services plugin in the Android Gradle setup used by your Flutter version.
3. Ensure `INTERNET` permission.
4. For Android 13+, request `POST_NOTIFICATIONS` at runtime.
5. Create notification channel before showing local notifications.
6. Confirm `minSdk` meets `firebase_messaging` requirements for your pinned plugin version.
7. If using R8/ProGuard, keep rules required by Firebase/Pusher plugins for your versions.

Do not copy obsolete `apply plugin:` snippets blindly — follow the FlutterFire docs matching your Flutter Gradle plugin generation.

---

## 28. iOS Configuration

Mark optional if the product is Android-only.

When iOS is supported:

1. Add `GoogleService-Info.plist`.
2. Enable Push Notifications capability.
3. Enable Background Modes → Remote notifications.
4. Upload APNs key/certificate in Firebase console.
5. Request notification permission at runtime.
6. Test on a physical device (simulator push is limited).

---

## 29. Testing Scenarios

### Customer

| Scenario | Expect |
|---|---|
| Request chat | Open + send/receive with assigned provider |
| Customer support | Open `support_customer`; admin replies show `support` |
| Foreground message | Pusher updates UI; no duplicate local notif if same chat open |
| Background FCM | Notification shown; tap opens conversation |
| Terminated tap | `getInitialMessage` routes correctly |
| Mute | Muted participant skipped in notify fan-out |
| Logout | Token cleared; device token removed/invalidated; channels closed |
| Reconnect | Missed messages recovered via refetch + dedupe |

### Provider

| Scenario | Expect |
|---|---|
| Assigned request chat | Works only while assigned |
| Provider support | `support_provider` works |
| Reassignment | Old provider gets `403`; conversation removed locally |
| Background FCM | Works with registered token |
| Reconnect | Actor + conversation resubscribe |

### Cross-actor

| Scenario | Expect |
|---|---|
| Customer → provider | Provider gets Pusher/FCM; customer does not self-notify |
| Provider → customer | Customer gets Pusher/FCM |
| Admin → support recipient | Correct customer/provider notified |
| Same numeric user/provider ids | Channels remain isolated by morph type |
| Unauthorized channel | Auth endpoint denies |
| Duplicate event / idempotent send | Single message row / single UI bubble |

---

## 30. Flutter Implementation Checklist

1. Add dependencies compatible with existing app architecture.
2. Configure Firebase client files (no server credentials).
3. Implement Dio client + secure Sanctum storage.
4. Implement login/logout with optional device-token cleanup.
5. Register FCM token via `/device-tokens` + refresh listener.
6. Implement Pusher connect + private authorizer to `/api/broadcasting/auth`.
7. Subscribe typed actor channel after login.
8. Build conversation list (cursor) + filters.
9. Build message history (cursor) + merge/dedupe.
10. Implement send + `client_message_id` idempotency.
11. Implement mark-read + unread badge via API/events.
12. Handle FCM foreground/background/terminated + taps.
13. Build notification inbox CRUD/read APIs.
14. Add lifecycle/connectivity reconnect.
15. Add logout cleanup.
16. Run customer, provider, and cross-actor test matrices.

---

## 31. Known Limitations

Confirmed from backend inspection only:

| Limitation | Detail |
|---|---|
| Text-only messages | No attachment upload API |
| No typing / presence | Not implemented |
| No message edit/delete HTTP APIs | Soft-delete capability exists on model/policy, but no chat route exposes delete |
| `.MessageRead` not broadcast | Read receipts are local/API only |
| `.ConversationUpdated` not broadcast | Status changes require REST refresh |
| No conversation-created event | Inbox relies on list refresh + notifications |
| Actor conversation `search` unused | Passed from controller but not applied in service |
| Synchronous fan-out latency | Send-message waits for Pusher/FCM attempts; no `queue:work` required |
| Support admins are not participants | Mobile apps are unaffected; admin unread math is weak |
| Legacy request message routes deprecated | Prefer conversation endpoints |

---

## Security reminder

- `PUSHER_APP_SECRET` belongs only on the Laravel server.
- Flutter must never contain the Pusher secret.
- Firebase service-account credentials belong only on the Laravel server.
- Flutter contains Firebase **client** configuration only.
- Private-channel authentication must send the Sanctum Bearer token.
- Frontends must never send `sender_id` or `sender_type`.
- The backend determines the sender from the authenticated token.

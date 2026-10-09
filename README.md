# Connect Server API

JSON REST API built with Symfony, Doctrine, Lexik JWT Authentication, and Gesdinet refresh tokens. All routes below are under `/api`. Routes require a valid access token unless explicitly described otherwise. Send it as `Authorization: Bearer <access_token>`.


## Authentication and workflow

1. Register with `POST /register` or `POST /users`, passing `email`, `password`, and `password_confirm` (both password fields must match; the password must have at least 8 characters). Registration returns `token`, `refresh_token`, `expires_in`, and the created user.
2. Log in with `POST /login` to obtain another access/refresh-token pair.
3. Use `GET /me` to check the authenticated identity.
4. When the access token expires, call `POST /token/refresh` with `{"refresh_token":"..."}`. Refresh tokens are single-use and stored hashed; use the replacement token returned by the bundle.
5. Create up to 10 servers with `POST /servers`. The creator is initially the `owner` and their creation count is retained even if ownership is later changed.
6. Configure server details in the create request or update them with `PATCH /servers/{uuid}`.
7. Find users by profile display name or user UUID with `GET /users/search?q=...`. Find public servers by server name or UUID with `GET /servers/search?q=...`.
8. Join a public server by its server UUID using `POST /servers/{uuid}/join`. Joining resolves only by UUID, not by server name or database ID. For a private server, an owner or editor can add a user by UUID using `POST /servers/{uuid}/members/{userUuid}`.
9. Owners and editors can create and manage text/voice channels. Owners can additionally manage member roles and remove members. A server must always have at least one owner.

## Server data

A server has a generated UUID, a creation timestamp in the `Europe/Berlin` timezone, a `member_count`, one settings object, and memberships. Settings contain:

```json
{
  "name": "My server",
  "description": "Optional description",
  "publicy": false,
  "profile_image": "https://example.test/server.png"
}
```

`profile_image` is an optional image URL/reference; uploading and storing image files is not part of this API. The server creation response also contains `uuid`, `member_count`, and `created_at`.

Memberships expose `user_uuid`, `role` (`member`, `owner`, or `editor`), and `joined_at`. Channels are associated with a server's settings and contain an integer `id`, UUID, custom `name`, and `type` (`text` or `voice`).

Text-channel messages retain the author's user relation, UUID, content, and creation timestamp. Only members of the channel's server can post messages or read channel history. Voice channels do not accept messages.

## Routes

### Authentication and account

| Method | Path | Access | Description |
| --- | --- | --- | --- |
| `POST` | `/register` | Public | Register an account with `email`, `password`, and matching `password_confirm`; then issue tokens. |
| `POST` | `/login` | Public | Verify credentials and issue tokens. |
| `POST` | `/token/refresh` | Public, refresh token required | Exchange the single-use refresh token. |
| `GET` | `/me` | Authenticated | Return the current user's public account data. |
| `POST` | `/users` | Public | Alias of registration; requires `email`, `password`, and matching `password_confirm`. |
| `GET` | `/users` | Authenticated | Return the current user's own account data. |
| `GET` | `/users/search?q={term}` | Authenticated | Search by profile display name or user UUID. Returns only UUID and display name (never email); limited to 25 results. |
| `GET` | `/users/{id}` | Authenticated, self only | Read own account. |
| `PUT` | `/users/{id}` | Authenticated, self only | Update own email and optional password. |
| `DELETE` | `/users/{id}` | Authenticated, self only | Delete own account. |
| `POST` | `/me/profile` | Authenticated | Create the current user's profile (`display_name`, `bio`). |
| `GET` | `/me/profile` | Authenticated | Read own profile. |
| `PUT` | `/me/profile` | Authenticated | Update own profile. |
| `DELETE` | `/me/profile` | Authenticated | Delete own profile. |

### Servers and memberships

| Method | Path | Access | Description |
| --- | --- | --- | --- |
| `POST` | `/servers` | Authenticated | Create a server and initial owner membership. Requires `name`; accepts optional `description`, `publicy`, and `profile_image`. Maximum 10 created servers per account (HTTP 409 beyond the limit). |
| `GET` | `/servers` | Authenticated | List public servers, optionally filtered by name or UUID using `?q={term}`; limited to 50 results. |
| `GET` | `/servers/search?q={term}` | Authenticated | Search public servers by name or UUID; returns only servers with `publicy: true`, limited to 50 results. |
| `GET` | `/me/servers` | Authenticated | List servers the current user has joined. |
| `GET` | `/servers/{uuid}` | Authenticated; member or public server | Read server settings and member count. |
| `PATCH`, `PUT` | `/servers/{uuid}` | Owner | Update `name`, `description`, `publicy`, and/or `profile_image`. |
| `DELETE` | `/servers/{uuid}` | Owner | Delete a server and its channels/members. |
| `GET` | `/servers/{uuid}/members` | Authenticated; member or public server | List member UUIDs, roles, and join times. |
| `POST` | `/servers/{uuid}/members` | Authenticated; public server | Join the current user as a `member`. |
| `POST` | `/servers/{uuid}/join` | Authenticated; public server | Join by the required server UUID (not name or database ID). Returns HTTP 404 for an unknown UUID and HTTP 409 if already a member. |
| `POST` | `/servers/{uuid}/members/{userUuid}` | Owner or Editor | Add an existing user to the server; optional `role` may be `member` or `editor` (editors can only add members). |
| `DELETE` | `/servers/{uuid}/members/me` | Authenticated member | Leave the server. The last owner must transfer ownership first. |
| `PATCH` | `/servers/{uuid}/members/{userUuid}` | Owner | Change the member's `role` to `member`, `editor`, or `owner`. |
| `DELETE` | `/servers/{uuid}/members/{userUuid}` | Owner | Remove a member. The last owner cannot be removed. |

### Channels

| Method | Path | Access | Description |
| --- | --- | --- | --- |
| `GET` | `/servers/{uuid}/channels` | Authenticated; member or public server | List channel names, IDs, and types. |
| `POST` | `/servers/{uuid}/channels` | Owner or Editor | Create a channel with `{"name":"general","type":"text"}` or `{"name":"Voice lounge","type":"voice"}`. |
| `PATCH`, `PUT` | `/servers/{uuid}/channels/{id}` | Owner or Editor | Update the channel `name` and/or `type`. |
| `DELETE` | `/servers/{uuid}/channels/{id}` | Owner or Editor | Delete a channel. |
| `PATCH` | `/channels/{channelUuid}` | Owner or Editor | Update a channel by UUID; accepts `name` and/or `type`. |
| `DELETE` | `/channels/{channelUuid}` | Owner or Editor | Delete a channel by UUID, including its message history. |

### Text-channel messages

| Method | Path | Access | Description |
| --- | --- | --- | --- |
| `GET` | `/channels/{channelUuid}/messages` | Authenticated server member; text channel | Read the newest history page, oldest to newest. Optional `limit` is 1–100 (default 50); use `before={messageUuid}` to fetch an older page. |
| `POST` | `/channels/{channelUuid}/messages` | Authenticated server member; text channel | Post `{"content":"Hello"}`. Content is required and limited to 4000 characters. Response includes message UUID, author UUID/name, and timestamp. |
| `PATCH` | `/channels/{channelUuid}/messages/{messageUuid}` | Authenticated server member; message author only | Edit your own message using `{"content":"Updated text"}`. |
| `DELETE` | `/channels/{channelUuid}/messages/{messageUuid}` | Authenticated server member; message author only | Delete your own message. |

## Frontend connection and credentials

There are no shared frontend credentials. Each user should log in with their own account using `POST /api/login`; keep the returned access token in application memory and never put account passwords, JWTs, or private keys in frontend source code or committed environment files.

For the provided Docker Compose setup, configure the frontend with:

```dotenv
VITE_API_BASE_URL=http://localhost:8080/api
VITE_CHAT_WEBSOCKET_URL=ws://localhost:8082
```

Use `wss://` for the WebSocket URL when the frontend is served over HTTPS in production. The API login response contains a `token` (the short-lived JWT access token) and a `refresh_token`. Refresh the access token through `POST /api/token/refresh` as documented above; authenticate a new socket with the replacement access token.

The WebSocket uses JSON text frames. Authenticate immediately after opening the connection, then subscribe to a text channel using its UUID:

```js
const socket = new WebSocket(import.meta.env.VITE_CHAT_WEBSOCKET_URL);

socket.addEventListener("open", () => {
  socket.send(JSON.stringify({ type: "authenticate", token: accessToken }));
});

socket.addEventListener("message", ({ data }) => {
  const event = JSON.parse(data);

  if (event.type === "authenticated") {
    socket.send(JSON.stringify({ type: "subscribe", channel_uuid: channelUuid }));
  } else if (event.type === "subscribed") {
    // Load GET /api/channels/{channelUuid}/messages for the initial history.
  } else if (event.type === "message") {
    // event.data has uuid, content, created_at, and author.
  } else if (event.type === "error") {
    console.error(event.message, event.errors);
  }
});

function sendChatMessage(content) {
  socket.send(JSON.stringify({
    type: "send_message",
    channel_uuid: channelUuid,
    content,
  }));
}
```

The server responds with `authenticated`, `subscribed`, and `message_accepted` acknowledgements. New messages are broadcast as `{ "type": "message", "data": { ... } }` to subscribed members. Messages posted through the REST API are also picked up and broadcast while the WebSocket server is running. To avoid missing messages while connecting, subscribe first, then fetch history and merge it with socket events by message UUID. Editing and deleting messages remain available through the REST endpoints and do not currently emit WebSocket events. A socket must authenticate again after its JWT expires.

Install PHP dependencies with `composer install`, then start both the API and WebSocket services with `docker compose up --build`; the WebSocket listener uses port `8082` (port `8081` remains available for phpMyAdmin).

## Example requests

Create a server:

```http
POST /api/servers
Authorization: Bearer <access_token>
Content-Type: application/json
```

```json
{
  "name": "Community",
  "description": "A place to chat",
  "publicy": true,
  "profile_image": "https://example.test/community.png"
}
```

Create a channel (the authenticated user must be an owner or editor):

```http
POST /api/servers/{uuid}/channels
Authorization: Bearer <access_token>
Content-Type: application/json
```

```json
{
  "name": "announcements",
  "type": "text"
}
```

## Persistence

Apply schema migrations after configuring `DATABASE_URL`:

```bash
php bin/console doctrine:migrations:migrate
```

# Push notifications architecture

## Existing data flow

CRM `EDS` is the source of operational data: users, clients, courses, schedules, workshops, camps, day camps, tickets, payments, consents and communication history. The CRM already has `Messages` and `MessagesRecipients` for SMS-style communication.

Laravel `mobile` keeps a local projection of CRM data through `CrmToMobileSync` jobs. The mobile API authenticates CRM users with Sanctum and uses `users.UsersID` as the stable user key. Flutter stores the Sanctum token and user GUID, then reads mobile data from Laravel.

Flutter did not have Firebase Messaging, local notifications or an in-app notification list before this change.

## Recommended flow

CRM should not send pushes directly to devices. CRM prepares a campaign and sends it to Laravel through `/api/crm/push/*`. Laravel stores the notification, resolves recipients from its local CRM projection, stores per-recipient rows, sends FCM/APNs messages and remains the source of truth for the app bell history.

```
CRM panel -> CRM PushNotifications proxy -> Laravel CRM push API -> DB history -> SendPushNotificationJob -> FCM/APNs -> Flutter
Flutter -> Laravel mobile push API -> device tokens, list, unread count, read/opened state
```

## Storage

Device tokens: `device_tokens` in Laravel, keyed by `user_id` (`users.UsersID`) and token hash.

History: `push_notifications` and `push_notification_recipients` in Laravel. CRM logs staff actions through `API/CRON_logs/push_notifications.log` and can query Laravel campaign status.

Segments: `push_segments.filters_json` stores reusable filter JSON and recipient counts.

## Segmentation model

Filters are JSON and support:

- `user_ids`, `user_guids`, `exclude_user_ids`
- `school_ids`, `localization_ids`, `cities`
- `age_from`, `age_to`, `birth_date_from`, `birth_date_to`
- `style_ids`, `group_ids`, `course_heading_ids`, `course_ids`
- `instructor_ids`, `schedule_event_ids`
- `workshop_ids`, `camp_ids`, `day_camp_ids`
- `status_ids`, `payment_status`: `paid`, `unpaid`, `overdue`
- `active`, `marketing_consent`
- `has_mobile_app`, `has_active_push_token`, `last_seen_from`, `last_seen_to`

## Endpoints

CRM:

- `POST /api/crm/push/preview-recipients`
- `POST /api/crm/push/notifications`
- `POST /api/crm/push/notifications/{id}/send`
- `POST /api/crm/push/notifications/{id}/schedule`
- `GET /api/crm/push/notifications/{id}/status`
- `POST /api/crm/push/test`

Flutter:

- `POST /api/mobile/device-tokens`
- `DELETE /api/mobile/device-tokens/{token}`
- `GET /api/mobile/notifications`
- `GET /api/mobile/notifications/unread-count`
- `POST /api/mobile/notifications/{id}/read`
- `POST /api/mobile/notifications/{id}/opened`
- `POST /api/mobile/notifications/read-all`

## Environment

Laravel:

- `CRM_PUSH_API_TOKEN`
- `FIREBASE_PROJECT_ID`
- `FIREBASE_SERVER_KEY` or future HTTP v1 credential provider
- `FIREBASE_CREDENTIALS`

CRM:

- `MOBILE_PUSH_API_URL`
- `MOBILE_PUSH_API_TOKEN`
- `PUSH_NOTIFICATIONS_FUNCTION_ID`

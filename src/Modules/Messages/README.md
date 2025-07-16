# Messages Module

A powerful admin notification system that allows Automattic team members to create and manage location-based messages throughout the WordPress admin interface.

## What's this?

This module provides a messaging system for WordPress admin that:

1. Allows creating targeted messages for specific admin pages
2. Supports different message types (info, warning, error, success)
3. Includes location-based filtering with include/exclude rules
4. Provides encrypted message storage
5. Integrates seamlessly with both classic admin and block editor interfaces

## Usage

### Managing Messages

Messages can be managed through the Atlantis admin menu under "Messages". From there you can:

- Create new messages
- Edit existing messages
- Activate/deactivate messages
- Delete messages
- View all active messages

### Message Properties

When creating or editing a message, you can set:

- **Name**: Internal identifier for the message
- **Content**: The message content (supports HTML)
- **Type**: Message type (info, warning, error, success)
- **Status**: Active or Inactive
- **Location**: Where the message should appear
- **Exclude**: Where the message should NOT appear

### Location Targeting

Messages can be targeted to specific admin locations:

- `all` - Show everywhere in admin
- `all_post_editors` - Show in all post/page editors
- Specific admin pages (e.g., 'plugins.php', 'edit.php')
- Custom post type screens
- Taxonomy screens
- Individual menu/submenu pages

### Example Usage

```php
// Display a message on all screens except the dashboard
[
    'message_name' => 'Global Notice',
    'message_content' => 'Important system maintenance scheduled.',
    'message_type' => 'warning',
    'message_status' => 'active',
    'message_location' => ['all'],
    'message_exclude' => ['index.php']
]

// Display a message only in post editors
[
    'message_name' => 'Editor Notice',
    'message_content' => 'New editing features available!',
    'message_type' => 'info',
    'message_status' => 'active',
    'message_location' => ['all_post_editors']
]
```

## Technical Details

### Database Schema

Messages are stored in the `{prefix}atlantis_messages` table with the following structure:

- `id` (bigint) - Auto-incrementing ID
- `message_name` (varchar) - Message identifier
- `message_content` (text) - Encrypted message content
- `message_type` (varchar) - Message type
- `message_status` (varchar) - Active/Inactive status
- `message_location` (text) - Serialized array of locations
- `message_exclude` (text) - Serialized array of excluded locations
- `message_time` (datetime) - Creation timestamp

### Security Features

1. Messages are only visible to Automattic team members
2. Message content is stored encrypted in the database
3. All form submissions include nonce verification
4. Input data is properly sanitized and validated

### Integration Points

The module integrates with WordPress in several ways:

1. Admin menu integration via `add_submenu_page()`
2. Admin notices via `admin_notices` hook
3. Block editor notices via `wp-edit-post` script
4. Custom database table for message storage

## Support

If you encounter any issues or have suggestions, please create an issue in the repository at:
https://github.com/a8cteam51/a8csp-atlantis/issues 
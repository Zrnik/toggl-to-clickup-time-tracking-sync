# Synchronize Toggl and ClickUp Time Tracking with Docker

## Usage Instructions: (Docker Required)

1. Clone this repository and duplicate `config.neon.dist` as `config.neon`.
2. Populate `config.neon` with your ClickUp and Toggl API keys.

Execute the following commands:

- Run `make build` to locally build the PHP image using Docker.
- Run `make upload` to sync your time entries from Toggl and upload them to ClickUp. The synchronization only affects Toggl entries **40 days old or newer**.

> Any creation, modification, or deletion of time entries in Toggl will be mirrored in ClickUp.

## ID Detection

Include the task ID in the format `#xyzabc0123` within the time entry description, and it will be automatically recognized.

>If multiple task IDs are provided, only the first one will be uploaded with the time entry.

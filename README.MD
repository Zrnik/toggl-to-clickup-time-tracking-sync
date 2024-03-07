# Toggl To ClickUp Time Tracking Sync

### How to use: (Docker Required)

Clone this repository, copy `config.neon.dist` to `config.neon` 
and fill it with your API keys from ClickUp and Toggl.

First run `make build` which will build the PHP image locally with docker.

Then run `make upload` which will synchronize your time entries from 
toggl and uploads them to ClickUp. The synchronization is only updated
for toggl entries **40 days old or newer**!

> If you create, modify or delete time entry in Toggl,
> it will be created, modified or deleted it in ClickUp too.

### Detecting IDs

You must include task id in format `#xyzabc0123` anywhere in the
time entry description, and it will automatically pick up.

If you provide multiple task ids, only the first one will have the 
time entry uploaded.

If you use custom task IDs, you still have to put `#` in front
of their name in toggl.

---
paths:
  - routes/console.php
---

# Routes

## No sub-minute scheduled tasks
Never use everySecond()/everyTenSeconds()/etc. here. Any sub-minute task makes every `schedule:run` stay alive for the whole minute (app-wide, and deploys then need `schedule:interrupt`), and it hangs the test suite forever: PurgeExpiredAccountsTest freezes time with travelTo() and calls `schedule:run`, so the minute never ends. Use everyMinute() and have the job re-dispatch itself if it needs to drain a backlog (see FlushApiRequestLogs). FlushApiRequestLogsTest asserts no API-log event isRepeatable().

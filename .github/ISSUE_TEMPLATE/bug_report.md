---
name: Bug Report
about: Report a bug in Glowing Fiesta
title: '[BUG] '
labels: 'bug'
assignees: ''
---

## Bug Description

A clear and concise description of what the bug is.

## Steps to Reproduce

1. Go to '...'
2. Send request '...'
3. Observe '...'
4. See error

## Expected Behavior

What should happen?

## Actual Behavior

What actually happens?

## Error Messages

```
Paste any error messages, stack traces, or logs here
```

## Environment

- **PHP Version:** 
- **Laravel Version:** 
- **MySQL Version:** 
- **Redis Version:** 
- **Operating System:** 
- **Deployment Environment:** (local/staging/production)

## Request Details

### API Endpoint
```
POST /api/metrics/counter
```

### Request Headers
```
X-API-Key: ***
Content-Type: application/json
```

### Request Body
```json
{
  "metric": "test.metric",
  "value": 1
}
```

### Response Status
```
500 Internal Server Error
```

### Response Body
```json
{
  "error": "message",
  "code": "ERROR_CODE"
}
```

## Database State

Any relevant information about the database state when the bug occurred?

## Screenshots

If applicable, add screenshots to help explain the problem.

## Impact

- [ ] Blocks deployments
- [ ] Prevents data ingestion
- [ ] Causes data loss
- [ ] Affects performance
- [ ] Security vulnerability
- [ ] Minor inconvenience

## Workaround

Is there a temporary workaround for this issue?

## Related Issues

<!-- Link any related issues -->

## Priority

- [ ] Critical (system down)
- [ ] High (major functionality broken)
- [ ] Medium (functionality impaired)
- [ ] Low (minor issue)

## Additional Context

Add any other context about the problem here.

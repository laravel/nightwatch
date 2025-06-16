- Request sampling should apply to unmatched routes
- Request sampling should apply to matched routes

Unmatched routes should be able to have their own custom sample rate.
Unmatched routes should fallback to the global sample rate.
Matched routes should be able to have their own custom sample rate.
Matched routes should fallback to the global sample rate.
Matched routes that are filtered out should be filtered out before a middleware returns early.

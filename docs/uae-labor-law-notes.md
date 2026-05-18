# UAE Labor Law Notes

This document is implementation guidance, not legal advice. Legal values must remain configurable through versioned rule tables.

## Sick Leave Defaults

- Maximum sick leave per year: 90 days.
- First 15 days: full pay.
- Next 30 days: half pay.
- Remaining 45 days: unpaid.
- Probation period: no paid sick leave by default.
- Employee notification: within 3 working days.
- Medical report: required.

## Annual Leave Defaults

- After 1 year of service: 30 days annual leave.
- More than 6 months but less than 1 year: 2 days per month.
- Unused leave on termination: paid based on basic salary.
- Annual leave while employed: full wage.

## Implementation Rule

Controllers and frontend screens should not hardcode legal values. They should read company policy, legal rule items, and service calculation snapshots.

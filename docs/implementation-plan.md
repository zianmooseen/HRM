# Implementation Plan

## Phase 1 - Foundation

- Create monorepo structure.
- Add environment examples and Docker infrastructure.
- Add API, schema, and product documentation.
- Add shared TypeScript contracts.

## Phase 2 - Backend API

- Initialize Laravel dependencies.
- Install Sanctum.
- Add migrations for core company, identity, employee, onboarding, compliance, leave, payroll, and audit tables.
- Add models, policies, Form Requests, API Resources, and seeders.
- Add tests for auth, permissions, and compliance calculations.

## Phase 3 - Frontend App

- Initialize Nuxt dependencies.
- Add dashboard shell, auth pages, protected middleware, Pinia store, and API client.
- Build company and employee management screens.
- Add leave, attendance, payroll, and compliance settings screens.

## Phase 4 - Compliance

- Implement sick leave, annual leave, gratuity, and Emiratisation service classes.
- Store calculation snapshots for auditability.
- Add admin screens for policy configuration and reports.

# AI Architecture and Data Minimization

## Architecture overview

The Phase 4 AI layer follows a server-side-only pattern:

PHP Application
↓
AI Context Builder
↓
Prompt Builder
↓
Gemini Service
↓
Gemini API
↓
AI Output Validator
↓
HR Review
↓
Database

All Gemini calls are executed from PHP server-side code using the `GeminiService` class with cURL. The browser never receives the API key and the app never exposes raw Gemini responses directly.

## Allowed employee data for AI requests

Only verified Core HR employee data is sent to Gemini. The system does not send credentials, tokens, or raw database records.

Allowed fields include:
- employee number
- first name and last name
- position name
- department name
- branch name
- employment status
- employment type
- date hired
- employment history entries from the verified history table

Restricted or excluded fields:
- password hashes
- authentication tokens
- session identifiers
- database credentials
- financial transaction records
- unrelated private data
- any field not directly needed for the selected AI task

If a field is unavailable, the context uses: `Not available`.

## Prompt and validation safeguards

- The prompt builder creates a controlled prompt for employee profiling or document drafting.
- The AI validator requires valid JSON and checks required fields before acceptance.
- Unsupported employment recommendations and invented facts are rejected.
- The application keeps the authoritative employee data in the Core HR database and treats AI output as draft content requiring HR review.

## Human approval requirement

AI-generated content is never treated as authoritative HR data. The application requires human review before approval and must never allow the AI itself to approve, reject, or finalize records.

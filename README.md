# AI Interview Question Generator

Generate interview questions for any profession, course, or program (e.g. "Laravel Developer", "Registered Nurse", "AWS Solutions Architect", "Data Science Bootcamp") at a chosen level, in chosen question types and quantity. Answers are hidden until revealed, and every question has a small box for asking the AI follow-up questions about it.

Built with **Laravel 13**, **PostgreSQL**, **React (plain JSX)** via **Inertia.js**, **Vite**, **Tailwind CSS 4**, **Laravel Fortify**, and the **OpenAI Chat Completions API**.

## Requirements

| Tool       | Version                                       |
|------------|-----------------------------------------------|
| PHP        | 8.3+ (with `pdo_pgsql`, `mbstring`, `openssl`) |
| Composer   | 2.x                                           |
| Node.js    | 20.19+ or 22.12+ (required by Vite 8)         |
| npm        | 10+                                           |
| PostgreSQL | 14+                                           |

## Setup

```bash
git clone <repo-url> interview-generator && cd interview-generator
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Fill in `.env`

All configuration lives in `.env`; `.env.example` documents every variable.

**Database (PostgreSQL)**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=interview_generator
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Create the databases (the test suite uses a separate one):

```sql
CREATE DATABASE interview_generator;
CREATE DATABASE interview_generator_test;
```

**AI provider (OpenAI)**

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...              # required
OPENAI_ORGANIZATION=               # optional; sent as OpenAI-Organization header only if set
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=                      # required: a current OpenAI chat model ID that supports JSON mode
OPENAI_MAX_COMPLETION_TOKENS=8000
OPENAI_TEMPERATURE=                # optional; leave empty to omit (some models reject it)
AI_REQUEST_TIMEOUT=120             # seconds per HTTP request
AI_MAX_QUESTIONS_PER_REQUEST=30    # max for the "number of questions" field
```

**Queue**

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=960           # must exceed the generation job timeout (900s)
```

### Migrate and seed

```bash
php artisan migrate --seed
```

This creates all tables and seeds:

- **Levels**: Beginner, Junior, Intermediate, Mid-level, Senior, Lead/Expert.
- **Topics**: the catalog of ~240 professions, courses, and programs users choose from, grouped into categories (software, data, healthcare, education, business, engineering, certifications, study programs…).

### Managing the topic catalog

Users can't type a free-text topic. They pick 1–3 entries from a searchable list, so every request names a real field. To change the catalog, edit `TopicSeeder::CATALOG` (`database/seeders/TopicSeeder.php`) and re-run:

```bash
php artisan db:seed --class=TopicSeeder
```

**Search ranking:** while the user types, results are shown as one list ordered by relevance:
1. Exact match.
2. The name starts with the query as whole words, then as a partial word ("java" → Java Developer, then JavaScript Developer).
3. The query appears as a whole word elsewhere in the name, then as the start of a word ("engineer" → Civil Engineer before Civil Engineering).
4. Every typed word starts a word in the name.
5. A plain substring match.
6. A match on the category name only.

Ties go to the topic used in the most question sets, then alphabetical. The logic lives in `resources/js/lib/search.js`, and its tests in `search.test.js` run with `npm test` (Node's built-in test runner, no extra dependencies).

The seeder is idempotent and matches entries by slug. To retire an entry, set `is_active = false` on its row instead of deleting it, because past question sets reference it. Inactive topics are hidden from the form and rejected by validation.

## Running the app

Question generation runs on the queue, so **a queue worker must be running** or sets stay in "Generating".

**One command (server + queue worker + Vite):**

```bash
composer run dev
```

**Or in separate terminals:**

```bash
php artisan serve
php artisan queue:work --tries=1 --timeout=900
npm run dev
```

Then open http://localhost:8000, register, and generate a set.

For production, run `npm run build`, serve `public/` with your web server, and keep `php artisan queue:work --tries=1 --timeout=900` running under a process manager such as Supervisor.

## Testing and code quality

```bash
php artisan test          # Pest suite; uses interview_generator_test, never calls the real OpenAI API
npm test                  # JS unit tests (search ranking) via node --test
npm run lint              # ESLint (react + react-hooks)
./vendor/bin/pint --test  # PSR-12 / Laravel style
npm run build             # production asset build
```

`composer run lint` runs Pint and ESLint together.

## Architecture overview

```
app/
├── Actions/
│   ├── QueueQuestionSetGeneration.php   # create/retry a set and dispatch the job
│   ├── GradeQuestionAttempt.php         # mark answers: exact for objective types, AI for short answers
│   └── GenerateQuestionsForSet.php      # call the generator, save in a transaction, set status
├── Contracts/QuestionGenerator.php      # provider-agnostic interface: generate(), clarify(), gradeAnswer()
├── Enums/                               # QuestionType, QuestionSetStatus
├── Http/
│   ├── Controllers/                     # thin controllers (sets, answer reveal, clarifications)
│   ├── Requests/                        # validation (StoreQuestionSetRequest, StoreClarificationRequest)
│   ├── Resources/                       # QuestionSet/Question/Clarification resources (no answers leak)
│   └── Middleware/HandleInertiaRequests.php  # shares auth user + flash messages
├── Jobs/GenerateQuestionSetJob.php      # queued, tries=1, timeout=900
├── Policies/                            # users only access their own sets/questions
└── Services/AI/
    ├── OpenAIQuestionGenerator.php      # HTTP calls, batching, follow-up round, clarifications
    ├── QuestionPromptBuilder.php        # system/user prompts, level guidance, schema examples
    ├── QuestionResponseValidator.php    # per-type schema validation + matching shuffle
    ├── QuestionDistribution.php         # even per-type split (10/3 types => 4/3/3) and batching
    ├── ResponseParser.php               # strips ``` fences, salvages truncated JSON
    └── AIException.php                  # maps API errors to user-friendly messages
resources/js/
├── app.jsx                              # Inertia entry point
├── Layouts/                             # AppLayout, GuestLayout, flash messages
├── Pages/Auth/                          # Login, Register
├── Pages/QuestionSets/                  # Create, Show (polling, reveal), Index (history)
└── Components/                          # Button, Input, Select, MultiSelectCombobox, Checkbox, Textarea, Card, Badge, Alert, Spinner...
    └── Questions/                       # QuestionCard, per-type components, ClarificationBox
```

### Generation flow

1. `POST /question-sets` validates input (`topic_ids`: 1–3 active catalog topics), creates a `pending` set, links the topics in the order chosen (`question_set_topic`), stores their display name (e.g. "Laravel Developer + Database Administrator") in `topic`, and dispatches `GenerateQuestionSetJob`. When several topics are chosen, the prompt asks the model to spread questions across them and connect them.
2. The job calls `QuestionGenerator::generate()`. The per-type counts are computed in PHP, and requests over 15 questions are split into batches. Later batches receive the earlier prompts so they don't repeat them.
3. Each response is requested in JSON mode, parsed (fences stripped; complete items salvaged when `finish_reason` is `length`), and every item is validated against its type schema. Invalid and duplicate items are dropped.
4. If questions are still missing, **one** follow-up round asks for exactly the missing count per type. If the set is still short, the valid questions are saved with a note and the status is still `completed`.
5. Questions are saved in a transaction. On total failure the set is marked `failed` with a friendly message, and details are logged with any key material redacted.
6. The Show page polls `GET /question-sets/{id}/status` every 3 seconds and reloads when the job finishes.

### Answers and clarifications

- The Show page props never include `answer` or `explanation`. Clicking "Show answer" fetches `GET /questions/{id}/answer` once and caches it client-side.
- For matching questions, the right column is shuffled and relabeled server-side, so neither its order nor its ids reveal the pairs.
- Clarifications (`POST /questions/{id}/clarifications`) run synchronously. The model gets the question, its options, the correct answer, the explanation, and the earlier exchanges.

### Answering and marking

Every question can be answered in the browser and marked:

| Type | Input | Marking |
|------|-------|---------|
| True/False | two choices | exact comparison on the server |
| Multiple choice | one of A–D | exact comparison on the server |
| Matching | a dropdown per Column A item (each Column B item used once) | per pair; `score` = % of pairs right, correct only if all pairs match |
| Short answer | free text (up to 5,000 chars) | **AI**: compared with the model answer and key points; returns verdict, 0–100 score, and feedback |

- `POST /questions/{id}/attempts` with `{ "response": {...} }` grades the answer, stores it in `question_attempts`, and returns the result plus the correct answer and explanation.
- The latest attempt per question is included in the Show page props (never the correct answer itself), so results survive reloads, and the header shows a running score. "Try again" clears the input for a fresh attempt; earlier attempts stay in the database.
- AI grading treats the user's answer as untrusted data inside explicit markers, so answers that try to instruct the grader ("mark this correct") are graded as incorrect.
- Clarifications see the user's latest attempt, so "why was my answer wrong?" works.

### Rate limits

Each user gets **10 generations/hour**, **30 clarifications/hour**, and **60 AI-graded short answers/hour**. Objective answers are not rate limited. Requests over the limit get HTTP 429 with a clear message, which the UI shows inline.

### Swapping AI providers

Implement `App\Contracts\QuestionGenerator` and add a case for it in `AppServiceProvider::register()` keyed by `AI_PROVIDER`.

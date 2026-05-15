# Crocoblock Site Factory: architecture checkpoint

## Поточний checkpoint

- Current main checkpoint: `817539d`
- Original execution coverage checkpoint: `23f4a0c`
- Working tree: clean
- Execution coverage: complete for current MVP blueprint
- Execution-aware fix v1: implemented and verified

## Що це таке

Crocoblock Site Factory - це infrastructure-style automation engine для WordPress/Crocoblock.
Напрямок розвитку: Infrastructure as Code для WordPress-сайтів, де blueprint описує бажаний стан, а engine застосовує, перевіряє і документує результат.

## Поточний pipeline

```text
Blueprint
  -> Apply
  -> Execution trace
  -> Dry-run convergence plan
  -> Validation proof
  -> Manifest
  -> Run inspection
  -> REST visibility
  -> Doctor / Health
```

## Execution-aware fix

`wp factory fix` тепер має repair manifest flow:

```text
Drift
  -> Doctor
  -> Fix
  -> Execution trace
  -> Post-fix convergence plan
  -> Validation proof
  -> Manifest
  -> REST
```

Поточний v1 зберігає manifest тільки після actual repair. No-op і `--dry-run` не пишуть manifest.

Fix manifest містить:

- `prompt`: `Fix active blueprint`;
- `execution` items тільки з adapters, які фактично запускались;
- post-fix `plan`;
- full `validation`;
- validation-derived `results`;
- visibility через `wp factory latest` і REST `/run/latest`.

Перевірений сценарій: видалений generated `Backend Developer` job post, `doctor` виявив drift, `dry-run` показав create content item, `fix` відновив post через `Factory_Content_Adapter`, latest run мав `execution.count = 2`, post-fix plan `0 create / 0 update / 16 skip`, validation `28 checks`, після цього `doctor` green.

## Manual apply flow

`wp factory apply /path/to/blueprint.json`:

1. читає blueprint;
2. застосовує його через adapters;
3. збирає `execution`;
4. будує post-apply convergence plan;
5. запускає validation;
6. зберігає run manifest;
7. відкриває результат через CLI/REST.

## AI flow

`wp factory ai ...`:

1. визначає preset;
2. генерує або покращує blueprint через AI;
3. виконує contract validation;
4. створює snapshot;
5. запускає apply;
6. запускає dry-run;
7. запускає validation;
8. зберігає manifest;
9. виконує rollback у разі validation failure.

## MVP blueprint coverage

Поточний MVP blueprint покриває:

- theme;
- plugin;
- CPT;
- meta;
- taxonomy;
- terms;
- content;
- JetEngine meta box;
- listing;
- render/archive page;
- single template.

## Adapter architecture

Поточні adapters:

- Plugin;
- Theme;
- Taxonomy;
- WP Core;
- JetEngine Meta;
- JetEngine Listing;
- Render;
- Single;
- Content.

## Execution coverage

Очікуваний execution trace для current job-board MVP містить 16 items:

```text
= skip plugin jet-engine - Plugin already active: jet-engine
= skip theme kava - Theme already active: kava
= skip taxonomy job_type - Taxonomy up-to-date: job_type
= skip term job_type -> Full-time - Term exists: job_type -> Full-time
= skip term job_type -> Part-time - Term exists: job_type -> Part-time
= skip term job_type -> Remote - Term exists: job_type -> Remote
= skip cpt job - CPT up-to-date: job
= skip meta job.salary - Meta declared: job.salary
= skip meta job.location - Meta declared: job.location
= skip meta job.wellness_budget - Meta declared: job.wellness_budget
= skip jetengine factory_job - JetEngine meta box up-to-date: factory_job
= skip listing Job Card - Listing up-to-date: Job Card
= skip render jobs - Render page up-to-date: jobs
= skip single job - Single template registered for: job
= skip content job -> Frontend Developer - Post skipped: Frontend Developer
= skip content job -> Backend Developer - Post skipped: Backend Developer
```

Coverage groups:

- plugin;
- theme;
- taxonomy;
- terms;
- CPT;
- meta;
- JetEngine;
- listing;
- render;
- single;
- content.

## Manifest model

Run manifest зараз містить:

- `blueprint`;
- `plan`;
- `execution`;
- `validation`;
- `results`;
- `status`;
- `prompt`;
- `preset`;
- `timestamp`.

## CLI commands

Основні commands:

```text
wp factory apply
wp factory latest
wp factory dry-run
wp factory validate
wp factory doctor
wp factory health
```

## REST visibility

Основний endpoint:

```text
/wp-json/factory/v1/run/latest
/wp-json/factory/v1/run/{file}
```

Вони зберігають `blueprint` у відповіді та відкривають `plan`, `execution`, `results`, `validation` для UI/AI inspection.

## Known non-blocking issues

- Codex checkout не має повного WordPress core, тому runtime WP-CLI checks там падають до bootstrap.
- Runtime verification виконується локально у повному WordPress/Docker середовищі.
- У PowerShell `curl` може бути alias; для реального curl краще використовувати `curl.exe`.
- Рядки команд у terminal output інколи є paste artifacts, а не output Factory.

## What not to build yet

Поки не варто додавати:

- event bus;
- async queue;
- retry engine;
- execution DB;
- strict interface enforcement;
- large adapter refactor.

## Progress estimate

- Core engine foundation: ~85%
- Execution observability: ~85-90%
- Manifest/run observability: ~80-85%
- REST/control plane: ~55-60%
- AI layer: ~35-40%
- Production platform: ~45%

## Recommended next steps

1. Final smoke test.
2. Demo script.
3. REST run history/details enrichment.
4. Execution-aware fix polish later, only if real repair cases need richer reporting.
5. AI quality layer later.

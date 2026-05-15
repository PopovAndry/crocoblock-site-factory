# Crocoblock Site Factory — Observable Blueprint Execution Demo

## Передумови

- Docker containers запущені.
- Поточний repo clean.
- Generated blueprint існує:

```text
/var/www/blueprints/generated/ai-blueprint.json
```

## Step 1 — confirm repository state

```powershell
git status
git log --oneline --decorate -5
```

Очікування: робоче дерево чисте, видимий поточний checkpoint branch/commit.

## Step 2 — confirm Docker state

```powershell
docker compose ps
```

Очікування: WordPress, database і WP-CLI середовище доступні.

## Step 3 — check runtime health

```powershell
docker compose run --rm wpcli wp factory health
docker compose run --rm wpcli wp factory doctor
```

Очікування:

- runtime health без критичних помилок;
- doctor показує, що desired state синхронізований або готовий до перевірки.

## Step 4 — show convergence plan

```powershell
docker compose run --rm wpcli wp factory dry-run /var/www/blueprints/generated/ai-blueprint.json
```

Очікуваний результат:

- 0 create;
- 0 update;
- 16 unchanged.

## Step 5 — apply blueprint and save manifest

```powershell
docker compose run --rm wpcli wp factory apply /var/www/blueprints/generated/ai-blueprint.json
```

Пояснення:

- apply застосовує blueprint;
- adapters виконують потрібні runtime/durable операції;
- engine збирає execution trace;
- engine будує post-apply convergence plan;
- engine валідовує WordPress state;
- engine зберігає run manifest.

## Step 6 — inspect latest run

```powershell
docker compose run --rm wpcli wp factory latest
```

Очікуваний output:

- Plan Summary;
- Execution items = 16;
- Validation checks = 28.

Очікувані execution categories:

- plugin;
- theme;
- taxonomy;
- terms;
- cpt;
- meta;
- jetengine;
- listing;
- render;
- single;
- content.

## Step 7 — validate and doctor

```powershell
docker compose run --rm wpcli wp factory validate
docker compose run --rm wpcli wp factory doctor
```

Очікування:

- validation complete;
- system healthy;
- all layers in sync.

## Step 8 — REST visibility

```powershell
$response = Invoke-RestMethod -UseBasicParsing http://localhost:8080/wp-json/factory/v1/run/latest

$response.status
$response.run.file
$response.run.prompt
$response.run.execution.count
$response.run.validation.count
$response.run.blueprint -ne $null
```

Очікуваний результат:

- `status`: `ok`;
- `execution.count`: `16`;
- `validation.count`: `28`;
- `blueprint`: `True`.

## Optional: demonstrate repair flow

Створіть тимчасовий eval-file, який видаляє generated `Backend Developer` post:

```powershell
@'
<?php
$post = get_page_by_title( 'Backend Developer', OBJECT, 'job' );

if ( $post ) {
	wp_delete_post( $post->ID, true );
	WP_CLI::log( 'Deleted Backend Developer job post.' );
} else {
	WP_CLI::log( 'Backend Developer job post was already missing.' );
}
'@ | Set-Content -Encoding UTF8 .\wp\tmp-delete-backend-developer.php

docker compose run --rm wpcli wp eval-file /var/www/html/tmp-delete-backend-developer.php
Remove-Item .\wp\tmp-delete-backend-developer.php
```

Покажіть drift:

```powershell
docker compose run --rm wpcli wp factory doctor
docker compose run --rm wpcli wp factory dry-run /var/www/blueprints/generated/ai-blueprint.json
```

Очікування:

- doctor показує `Missing content item: job -> Backend Developer`;
- dry-run показує `+ Create content item: job -> Backend Developer`.

Виконайте repair:

```powershell
docker compose run --rm wpcli wp factory fix
docker compose run --rm wpcli wp factory latest
```

Очікування:

- `fix` recreates `Backend Developer`;
- latest prompt: `Fix active blueprint`;
- execution items: `skip Frontend Developer`, `create Backend Developer`;
- `execution.count`: `2`;
- post-fix plan: `0 create / 0 update / 16 skip`;
- validation checks: `28`.

REST verification:

```powershell
$repair = Invoke-RestMethod -UseBasicParsing http://localhost:8080/wp-json/factory/v1/run/latest

$repair.status
$repair.run.prompt
$repair.run.execution.count
$repair.run.validation.count
```

Фінальна перевірка:

```powershell
docker compose run --rm wpcli wp factory validate
docker compose run --rm wpcli wp factory doctor
```

Очікування: validation complete, doctor green.

## Demo narration

Під час demo варто підкреслити:

- blueprint є desired state;
- apply виконує adapters у стабільному порядку;
- execution trace показує, що фактично сталося;
- dry-run після apply доводить convergence;
- validation доводить фактичний WordPress state;
- manifest зберігає історію run;
- REST відкриває ці дані для UI/AI.

## Known demo notes

- Для deterministic demo використовуйте manual apply.
- Уникайте `wp factory ai --no-cache` під час demo, бо AI може змінити content values.
- Codex runtime не може виконати повні WP checks, бо там немає повного WordPress core.
- У PowerShell `curl` є alias; для REST demo використовуйте `Invoke-RestMethod`.

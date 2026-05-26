# Vending Machine

Framework-free PHP 8.4 solution for the senior backend vending machine challenge. The focus is the domain model: machine state, product selection, coin handling, and exact-change rules.

The project is intended to be evaluated through Docker. No local PHP or Composer installation is required.

## Quick path

If the target machine only has Docker, this is the full happy path:

```bash
docker compose run --rm php composer install
docker compose run --rm php
```

The first command installs dependencies. The second starts the interactive vending machine CLI.
`stdin_open` and `tty` are already enabled in `docker-compose.yml`, so `docker compose run --rm php` is the intended interactive evaluation path.

If you want to run the full quality suite instead of the CLI:

```bash
docker compose run --rm php composer check
```

## Run commands

```bash
docker compose run --rm php composer test
docker compose run --rm php composer analyse
docker compose run --rm php composer ecs:check
docker compose run --rm php composer ecs:fix
docker compose run --rm php composer check
```

## CLI usage

Run the interactive adapter from Docker with:

```bash
docker compose run --rm php
```

That service starts the CLI by default. If you prefer being explicit, this does the same thing:

```bash
docker compose run --rm php composer cli
```

Available commands:

- `0.05`
- `0.10`
- `0.25`
- `1`
- `GET-<PRODUCT-NAME>` such as `GET-WATER`, `GET-JUICE`, `GET-SODA`, or `GET-SPARKLING-WATER` when that product exists in the current catalog
- `RETURN-COIN`
- `SERVICE` resets the catalog and change reserve to the challenge setup while preserving currently inserted money
- `STATUS`
- `HELP`
- `EXIT`

Legacy developer-style aliases are still accepted internally: `insert <cents>`, `select <code>`, and `return-coins`.

Example session:

```text
> STATUS
> 1
> GET-WATER
> RETURN-COIN
> EXIT
```

## Docker

```bash
docker build -t vending-machine .
docker compose run --rm php composer install
docker compose run --rm php
docker compose run --rm php composer check
```

## Optional local commands

If you already have PHP 8.4 and Composer locally, the same commands can also be run without Docker.

```bash
composer cli
composer check
```

## Architecture

The solution is intentionally small and centered on the domain.

| Area | Decision |
|------|----------|
| Core model | `VendingMachine` is the main domain object and owns machine state transitions. |
| Money | All money is modeled in integer cents. No floats are used in the domain. |
| Products | Products are dynamic and identified by a `ProductSelection` value object instead of enums. The default challenge catalog is Water 65c, Juice 100c, Soda 150c. |
| Buy outcomes | Expected business outcomes return explicit result objects instead of exceptions. |
| Coin handling | `Coin` represents a physical coin value; the machine decides which denominations it accepts. |
| Change | Change comes only from the machine reserve, never from coins inserted in the current purchase. |

## Domain rules and assumptions

The detailed business assumptions live in [`docs/domain-assumptions.md`](docs/domain-assumptions.md).

Important rules in this implementation:

- accepted denominations are fixed to `5`, `10`, `25`, and `100` cents, following the challenge spec
- invalid coins are rejected immediately and returned
- `Return Coin` returns the exact inserted coins
- `SERVICE` replaces the catalog and machine change reserve with the challenge catalog, while preserving currently inserted money
- if funds are insufficient, stock is empty, or exact change cannot be returned, the machine does not vend and the inserted money is preserved

## Testing strategy

The test suite is split by intent:

- **Acceptance tests** cover end-to-end business scenarios such as buying products, returning coins, invalid coin rejection, service behavior, and failure flows.
- **Domain tests** cover invariants and tricky change-calculation paths directly.

This keeps the main behavior readable while still protecting dense logic and invariants.

## Tradeoffs

- Accepted denominations are fixed to the challenge specification instead of being configurable. This keeps the first version aligned with the problem statement and avoids speculative flexibility.
- No framework, database, or HTTP layer is included yet. The submission optimizes for domain clarity over delivery mechanism.
- `SERVICE` is kept close to the challenge wording even though a name like `reconfigure()` could be more explicit.

## Project structure

```text
src/Domain/
tests/Acceptance/
tests/Domain/
docs/domain-assumptions.md
```

## Review path

If you are reviewing the submission, the fastest order is:

1. `docs/domain-assumptions.md`
2. `src/Domain/VendingMachine.php`
3. `tests/Acceptance/VendingMachineAcceptanceTest.php`
4. `tests/Domain/`

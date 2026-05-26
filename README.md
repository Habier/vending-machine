# Vending Machine

Framework-free PHP 8.4 solution for the senior backend vending machine challenge. The focus is the domain model: machine state, product selection, coin handling, and exact-change rules.

## Quick path

1. Install dependencies:

```bash
composer install
```

2. Run the full quality suite:

```bash
composer check
```

3. If you want formatting fixes applied automatically:

```bash
composer ecs:fix
```

## Run commands

```bash
composer test
composer analyse
composer ecs:check
composer check
```

## Docker

```bash
docker build -t vending-machine .
docker compose run --rm php composer install
docker compose run --rm php composer check
```

## Architecture

The solution is intentionally small and centered on the domain.

| Area | Decision |
|------|----------|
| Core model | `VendingMachine` is the main domain object and owns machine state transitions. |
| Money | All money is modeled in integer cents. No floats are used in the domain. |
| Products | Products are dynamic and identified by a `ProductSelection` value object instead of enums. |
| Buy outcomes | Expected business outcomes return explicit result objects instead of exceptions. |
| Coin handling | `Coin` represents a physical coin value; the machine decides which denominations it accepts. |
| Change | Change comes only from the machine reserve, never from coins inserted in the current purchase. |

## Domain rules and assumptions

The detailed business assumptions live in [`docs/domain-assumptions.md`](docs/domain-assumptions.md).

Important rules in this implementation:

- accepted denominations are fixed to `5`, `10`, `25`, and `100` cents, following the challenge spec
- invalid coins are rejected immediately and returned
- `Return Coin` returns the exact inserted coins
- `SERVICE` replaces the catalog and machine change reserve, while preserving currently inserted money
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

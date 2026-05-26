# Vending Machine

Framework-free PHP 8.4 solution to simulate a vending machine.

## Quick Start

Build the reviewer image and start the interactive CLI:

```bash
docker build -t habier/vending-machine .
docker run --rm -it habier/vending-machine
```

## Run commands

```bash
docker run --rm habier/vending-machine composer test
docker run --rm habier/vending-machine composer analyse
docker run --rm habier/vending-machine composer ecs:check
docker run --rm habier/vending-machine composer ecs:fix
docker run --rm habier/vending-machine composer check
```

Available commands:

- `0.05`
- `0.10`
- `0.25`
- `1`
- `GET-<PRODUCT-NAME>` such as `GET-WATER`, `GET-JUICE` or `GET-SODA`
- `RETURN-COIN`
- `SERVICE` resets the catalog and change reserve to the challenge setup while preserving currently inserted money
- `STATUS`
- `HELP`
- `EXIT`

Example sessions:

```text
> STATUS
> 1
> GET-WATER
> EXIT
```

```text
> STATUS
> 0.10
> 0.10
> RETURN-COIN
> EXIT
```

## Docker

Primary reviewer artifact:

```bash
docker build -t habier/vending-machine .
docker run --rm -it habier/vending-machine
docker run --rm habier/vending-machine composer check
```

The image is self-contained: it installs Composer dependencies during `docker build` and copies the application source into the image, so reviewers do not need a bind mount or a separate `composer install` step.

Docker Compose remains available as a convenience wrapper around the same image:

```bash
docker compose build
docker compose run --rm php
docker compose run --rm php composer check
```

If you already have PHP 8.4 and Composer locally, the same commands can also be run without Docker.

## Architecture

The solution is intentionally small and centered on the domain.

| Area | Decision |
|------|----------|
| Core model | `VendingMachine` is the main domain object and owns machine state transitions. |
| Money | All money is modeled in integer cents. No floats are used in the domain. |
| Products | Products are dynamic and identified by a `ProductSelection` value object instead of enums. The default challenge catalog is Water 65c, Juice 100c, Soda 150c. |
| Buy outcomes | Expected business outcomes return explicit result objects instead of exceptions. |
| Coin handling | `Coin` represents a physical coin value, `Coins` remains the generic collection, and `ChangeReserve` names the machine's available change pool. |
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

- Accepted denominations are fixed to the challenge specification instead of being configurable. I decided to avoid speculative flexibility in favor of simplicity
- No persistence, since the challenge spec does not require it.
- `ChangeReserve` currently stores reserve coins as a coin collection because the challenge scale is small and clarity is preferred. If reserve size or accounting complexity grew, its internal representation could evolve to denomination counts without changing the domain boundary.

## Project structure

```text
src/Domain/
tests/Acceptance/
tests/Domain/
docs/domain-assumptions.md
```

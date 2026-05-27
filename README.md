# Vending Machine

Framework-free PHP 8.4 solution to simulate a vending machine.

## Quick Start

Build the reviewer image and start the interactive CLI:

```bash
docker build -t habier/vending-machine .
docker run --rm -it habier/vending-machine
```

## Available commands:

- `0.05`
- `0.10`
- `0.25`
- `1`
- `GET-<PRODUCT-NAME>` such as `GET-WATER`, `GET-JUICE` or `GET-SODA`
- `RETURN-COIN`
- `SERVICE` starts an interactive service flow to replace the change reserve and product catalog while preserving currently inserted money
- `STATUS`
- `HELP`
- `EXIT`

During `SERVICE`, omitted coin denominations are treated as zero and omitted products are removed because the configuration replaces the full machine setup.

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

Short interactive `SERVICE` session:

```text
> SERVICE
SERVICE mode.
Enter change reserve as denomination:quantity pairs, comma-separated.
Example: 1:33,0.25:66,0.10:22,0.05:44
Change reserve:

> 1:2,0.25:1,0.10:3,0.05:4
Change reserve saved.
Enter products one per line as name|price|stock.
Example: Water|0.65|5
Type DONE when finished.

> Water|0.65|5
Added Water at 65c with stock 5. Enter another product or DONE.

> Juice|1|3
Added Juice at 100c with stock 3. Enter another product or DONE.

> DONE
Machine serviced. Loaded 2 products. Change reserve total: 275c.
```

Service flow rules:

- change reserve input uses `denomination:quantity`, comma-separated, and only accepts `0.05`, `0.10`, `0.25`, and `1`
- products are entered one per line as `name|price|stock`
- finish product entry with `DONE`
- duplicate product names are rejected after `trim` and case-insensitive normalization, so `Water`, ` water `, and `WATER` are treated as the same product
- product prices are parsed in the CLI and stored in the domain as integer cents

## Docker
The image is self-contained: it installs Composer dependencies during `docker build` and copies the application source into the image, so reviewers do not need a bind mount or a separate `composer install` step.

Docker Compose remains available as a convenience wrapper around the same image:


## Run commands

```bash
docker run --rm habier/vending-machine composer test
docker run --rm habier/vending-machine composer analyse
docker run --rm habier/vending-machine composer ecs:check
docker run --rm habier/vending-machine composer ecs:fix
docker run --rm habier/vending-machine composer check
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
- `SERVICE` replaces the catalog and machine change reserve from interactive service input, while preserving currently inserted money
- if funds are insufficient, stock is empty, or exact change cannot be returned, the machine does not vend and the inserted money is preserved

## Testing strategy

The test suite is split by intent:

- **Acceptance tests** cover end-to-end business scenarios such as buying products, returning coins, invalid coin rejection, service behavior, and failure flows.
- **Domain tests** cover invariants and tricky change-calculation paths directly.
- **CLI tests** cover command parsing, dynamic product resolution, and interactive `SERVICE` validation behavior.

This keeps the main behavior readable while still protecting dense logic, adapter behavior, and domain invariants.

## Tradeoffs

- Accepted denominations are fixed to the challenge specification instead of being configurable. I decided to avoid speculative flexibility in favor of simplicity
- No persistence, since the challenge spec does not require it.
- `ChangeReserve` stores coins as a collection because the challenge scale is small and this stays clear and fast enough for hundreds of coins. If that changed, the internal representation could evolve without changing the domain boundary.


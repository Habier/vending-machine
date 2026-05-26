# Vending Machine Domain Assumptions

First vertical slice rules:

| Topic | Decision |
|-------|----------|
| Aggregate | `VendingMachine` is the core domain object. |
| Products | Products are dynamic. No enums. Product identity is a selection code string wrapped in a value object. |
| Challenge catalog | The default reviewer-facing catalog is Water `65`, Juice `100`, Soda `150` cents. |
| Money | Money uses integer cents only. No floats anywhere in the domain. |
| Coins | `Coins` is the generic coin collection; `ChangeReserve` wraps the machine's available change pool. |
| Accepted denominations | The machine accepts only `5`, `10`, `25`, and `100` cent coins. |
| Service mode | `service()` replaces catalog and machine change reserve, but preserves currently inserted coins. |
| Invalid coins | Invalid coins are rejected immediately and returned on the spot through `InsertCoinResult`. |
| Buy failures | Expected selection outcomes use explicit result objects, never exceptions. |
| Insufficient funds | Do not vend. Keep inserted coins untouched. |
| Out of stock | Do not vend. Keep inserted coins untouched. |
| Exact change unavailable | Do not vend. Keep inserted coins untouched. |
| Change source | Inserted coins are not used to make change for the current purchase. |
| Return coin | Returning coins emits the exact inserted coins and clears the inserted balance. |
| Unknown selection | Missing or removed selections return `UnknownProductSelection` as a normal result. |
| Change order | Change is emitted in descending denomination order. |
| Construction errors | Exceptions are acceptable only for malformed domain construction or impossible configuration. |

Implementation assumption:

- After a successful vend, the inserted coins move into the machine reserve only after change calculation succeeds. This preserves the rule that inserted coins cannot fund change for the same purchase while still keeping machine state coherent for later purchases.

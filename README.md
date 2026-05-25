# Vending Machine

## Setup

```bash
composer install
```

## Run Tooling

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

# OmniShard-1B: High-Performance Database Sharding Architecture

OmniShard-1B is a distributed database system designed to handle high-velocity data (up to 1 Billion records). It uses Laravel 12 and Docker to demonstrate advanced sharding, read-write splitting, and Redis-based search routing.

## 🚀 Core Features
- **Database Sharding:** Horizontal distribution of data across MySQL nodes.
- **Read-Write Splitting:** Master handles writes, Replicas handle reads.
- **Redis Bloom Filter:** Multi-key (Email & Phone) existence checking to save DB resources.
- **Smart Routing:** Instant shard identification using Redis Hash Maps.
- **Unique Global IDs:** Custom ID generation to avoid cross-shard primary key conflicts.

## 🛠 Tech Stack
- **Framework:** Laravel 12 (PHP 8.2)
- **Database:** MySQL 8.0 (1 Metadata, 2 Masters, 2 Replicas)
- **Cache/Filter:** Redis (Alpine)
- **Infrastructure:** Docker & Docker Compose

## 💻 Installation & Setup

### Step 1: Environment Setup
Clone the repository and prepare your environment file:
```bash
cp .env.example .env
composer install

## Step 2: Spin up Containers
Launch the entire infrastructure using Docker Compose:
```bash
docker compose up -d

### Step 3: Database Preparation
Run migrations on all nodes to establish the table structures:
```bash
docker exec -it omnishard-app php artisan migrate --database=metadata
docker exec -it omnishard-app php artisan migrate --database=shard_1
docker exec -it omnishard-app php artisan migrate --database=shard_2

### Step 4: Data Seeding
Inject 1 Million records into the shards (This might take a few minutes):
```bash
docker exec -it omnishard-app php artisan db:seed --class=MassiveUserSeeder

### Step 5: Sync Redis Layer
Synchronize Email and Phone data with the Redis Bloom Filter and Mapping layer:
```bash
docker exec -it omnishard-app php artisan sync:bloom

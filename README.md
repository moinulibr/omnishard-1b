# OmniShard-1B 🚀
An enterprise-grade, horizontally sharded user management system designed to handle 1 Billion+ records with ultra-low latency.

## 🏗️ Architecture Overview
The system uses **Horizontal Sharding** to split the user table across multiple database instances. A **Centralized Topology Manager** controls data routing, while a **Redis Bloom Filter** acts as a high-speed barricade to prevent duplicate registrations without hitting the database.



## ✨ Key Features
- **Dynamic Sharding:** Supports multiple phases and regions (e.g., Asia, Europe).
- **Bloom Filter Protection:** Uses Redis Bitmaps for O(1) existence checks.
- **Failover Search:** Intelligent fallback to shard-scanning if metadata fails.
- **Dockerized Environment:** Isolated shards, metadata DB, and Redis instances.

## 🛠️ Installation Guide (Local Setup)

### 1. Clone the repository
```bash
git clone [https://github.com/yourusername/omnishard-1b.git](https://github.com/yourusername/omnishard-1b.git)
cd omnishard-1b

### 2. Copy the .env.example and configure your database connections for metadata, shard_1, and shard_2
```bash
cp .env.example .env

### 3. Docker Infrastructure
Fire up the distributed environment:
```bash
docker-compose up -d

### 4. Database Migrations
Run migrations for all shards using the custom sharding command:
```bash
php artisan migrate:shards

### 5. Performance Testing (Mass Ingestion)
To simulate high load, use the optimized batch seeder:
```bash
php artisan db:seed --class=MassUserSeeder


📂 Project Structure
app/Services/ShardingConfig.php: The brain of the sharding topology.

app/Services/BloomFilterService.php: Redis-based membership logic.

app/Repositories/UserRepository.php: Data access layer for distributed nodes.

app/Utils/PhoneFormatter.php: Centralized normalization for consistent hashing.

🚀 API Endpoints
POST /api/users: High-speed registration with Bloom Filter check.

POST /api/login: Multi-shard authentication.

PUT /api/users/{id}: Shard-aware profile updates.

📄 License
MIT License. Free for learning and scaling!
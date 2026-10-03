# Architecture

This document describes the architecture of the **Dockerized Grocery Store** application across its local Docker Compose environment and Kubernetes (Kind) deployment.

The architecture is organized into four layers:

1. System Architecture
2. Docker Architecture
3. Kubernetes Architecture
4. Project Structure

---

# 1. System Architecture

The Dockerized Grocery Store follows a **two-tier application architecture** consisting of a PHP/Apache web application and a MySQL database.

The user accesses the application through a web browser. The web application processes HTTP requests and communicates with the MySQL database through the container network.

## Architecture

<p align="center">
  <img src="../architecture/system-architecture.png" alt="System Architecture Diagram" width="100%">
</p>

### Architecture Flow

```
User
  │
  ▼
Web Browser
  │
  │ HTTP Request
  ▼
Web Container
PHP 8.2 + Apache 2.4
  │
  │ Database Connection
  ▼
MySQL Database Container
MySQL 8.0
```

### Main Components

| Component | Responsibility |
| :--- | :--- |
| **Web Browser** | Provides the user interface and sends HTTP requests |
| **PHP 8.2** | Executes the application logic |
| **Apache 2.4** | Serves the PHP application and handles HTTP requests |
| **MySQL 8.0** | Stores and manages application data |
| **Docker Network** | Provides communication between application and database containers |

The application and database are separated into independent containers, allowing each component to be managed and deployed independently.

---

# 2. Docker Architecture

The application is containerized using Docker and orchestrated locally using Docker Compose.
Docker Compose defines the application and database services, their networking, port mappings, volumes, and initialization behavior.

## Detailed Docker Architecture


<p align="center">
  <img src="../architecture/docker-architecture.png" alt="Docker & Docker Compose Architecture" width="100%">
</p>


### 2.1 Web Container
The `grocery-web` container runs the application using:

* PHP 8.2
* Apache 2.4
* Grocery Store application source code

The application is exposed through the host using port mapping:

```
Host Port       Container Port
    8080    →        80
```

Therefore, the application can be accessed locally through: `http://localhost:8080`

### 2.2 Database Container
The `grocery-db` container runs:

* MySQL 8.0
* Grocery Store database
* Persistent database storage

The database uses:

```
Host Port       Container Port
    3307    →      3306
```

The application container communicates with MySQL internally through the Docker network rather than relying on the host IP address.

### 2.3 Container Networking
Docker provides an isolated bridge network for communication between the application and database containers.

```
grocery-web
     │
     │ MySQL connection
     ▼
Docker Bridge Network
     │
     ▼
grocery-db
```

The application uses the database service/container name (`mysql`) for internal communication. This avoids hard-coding container IP addresses.

### 2.4 Storage
The Docker environment uses two different storage mechanisms:

* **Application Bind Mount:** `./app → /var/www/html`  
  The application source code is mounted into the web container. This allows application files to be modified without rebuilding the container image during local development.
* **MySQL Named Volume:** `mysql-data → /var/lib/mysql`  
  The named volume stores MySQL data outside the container lifecycle. Therefore, database data can survive container recreation or restart.

### 2.5 Database Initialization
The initial database schema and data are provided through `database/grocery.sql`.
The SQL file is used during database initialization to create and populate the Grocery Store database.

---

# 3. Kubernetes Architecture

The application can be deployed to a local Kubernetes cluster using Kind (Kubernetes in Docker).
The Kubernetes deployment introduces orchestration capabilities such as service discovery, load balancing, multiple application Pods, persistent storage, configuration management, and self-healing.

## Kubernetes Architecture Traffic Flow

<p align="center">
  <img src="../architecture/kubernetes-architecture.png" alt="Kubernetes Cluster Architecture" width="100%">
</p>

### 3.1 Kind Cluster
The application is deployed inside a local Kind cluster. Kind provides a Kubernetes environment running locally using container-based nodes.
The cluster contains the application and database workloads required by the Grocery Store application.

### 3.2 Ingress Layer & Local Domain
The NGINX Ingress Controller provides external HTTP/HTTPS routing into the Kubernetes cluster.
The application is accessed locally via `http://grocery.local:8081`, resolved via `/etc/hosts` pointing to `127.0.0.1:8081`.
The Ingress resource routes incoming traffic to `grocery-app-service:80`.

### 3.3 Application Service
The `grocery-app-service` provides a stable network endpoint for the application Pods.
Instead of connecting directly to individual Pods, traffic is sent to the Service, which load-balances traffic across available replicas.

### 3.4 Application Pods
The application layer contains multiple PHP/Apache Pods (Replicas: 2).
Each application Pod runs PHP 8.2, Apache 2.4, and the Grocery Store Application.
Multiple Pods provide application-layer redundancy and allow Kubernetes to distribute incoming traffic across replicas.

### 3.5 ConfigMap
The Kubernetes ConfigMap stores non-sensitive application configuration (e.g., `DB_HOST`, `DB_NAME`). It allows configuration to be separated from the application container image.

### 3.6 Secret
The Kubernetes Secret is used for sensitive configuration data such as database credentials (`DB_USER`, `DB_PASSWORD`). Sensitive configuration is separated from normal application configuration.

### 3.7 MySQL Service
The `mysql-service` provides a stable network identity for the MySQL database workload.
Application Pods connect to MySQL through this internal Kubernetes Service instead of directly addressing the database Pod IP.

### 3.8 MySQL StatefulSet
MySQL is deployed using a Kubernetes StatefulSet.
The StatefulSet provides a stable identity (`mysql-0`) for the database workload and is appropriate for stateful applications that require persistent storage.

### 3.9 Persistent Storage
Persistent database storage is provided through a PersistentVolumeClaim (PVC).
The PVC ensures that MySQL data is not tied exclusively to the lifecycle of the database Pod.

---

# 4. Project Structure

The project separates application source code, database initialization, Docker configuration, and Kubernetes manifests.

```
Dockerized-Grocery-Store/
│
├── app/
│   ├── admin/
│   │   ├── LICENSE
│   │   ├── README.md
│   │   ├── .travis.yml
│   │   ├── package.json
│   │   ├── package-lock.json
│   │   ├── vendor/
│   │   └── ...
│   ├── css/
│   ├── fonts/
│   ├── images/
│   ├── js/
│   ├── theme/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── products.php
│   ├── checkout.php
│   ├── payment.php
│   ├── dbcon.php
│   └── ...
│
├── database/
│   └── grocery.sql
│
├── k8s/
│   ├── namespace.yaml
│   ├── config/
│   ├── database/
│   ├── app/
│   └── ingress/
│
├── architecture/
│   ├── banner.png
│   ├── system-architecture.png
│   ├── docker-architecture.png
│   └── kubernetes-architecture.png
│
├── docs/
│   ├── architecture.md
│   ├── deployment-runbook.md
│   ├── troubleshooting.md
│   └── validation-checklist.md
│
├── screenshots/
│   ├── application.png
│   ├── cluster.png
│   ├── database.png
│   ├── deployment-success.png
│   ├── docker-build.png
│   ├── docker-compose.png
│   ├── ingress.png
│   ├── pods.png
│   ├── self-healing.png
│   ├── service.png
│   └── storage.png
│
├── Dockerfile
├── docker-compose.yml
├── kind-config.yaml
├── grocery-backup.sql
├── README.md
├── LICENSE
└── .gitignore
```

### Directory Responsibilities

| Directory / File | Purpose |
| :--- | :--- |
| **app/** | Contains the Grocery Store application source code |
| **database/** | Contains database initialization SQL |
| **k8s/** | Contains organized Kubernetes manifests |
| **architecture/** | Contains architecture diagrams and project banner |
| **docs/** | Contains deployment, troubleshooting, validation, and architecture documentation |
| **screenshots/** | Contains deployment and validation evidence |
| **Dockerfile** | Defines the web application container image |
| **docker-compose.yml** | Defines local Docker Compose services |
| **kind-config.yaml** | Configures the local Kind cluster |
| **.gitignore** | Defines files excluded from version control |
| **README.md** | Provides project documentation |

---

# Architecture Summary

The Grocery Store application uses the same application concept across two deployment environments:

### Docker Compose
```
Browser
   │
   ▼
grocery-web
PHP + Apache
   │
   ▼
Docker Network
   │
   ▼
grocery-db
MySQL
```

### Kubernetes / Kind
```
Browser
   │
   ▼
NGINX Ingress
   │
   ▼
Application Service
   │
   ├── App Pod 1
   │
   └── App Pod 2
          │
          ▼
    MySQL Service
          │
          ▼
       mysql-0
          │
          ▼
         PVC
```

The Docker Compose environment provides containerized local development, while the Kubernetes deployment adds orchestration, service discovery, replica management, persistent storage, and automated workload management.

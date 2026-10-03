# Validation Checklist

This document provides a structured validation checklist for the **Dockerized Grocery Store** application.

The checklist is used to verify that the application, Docker environment, database, Kubernetes deployment, networking, storage, and configuration are working as expected.

---

# 1. Repository & Configuration Validation

## Project Structure

- [ ] `app/` directory exists
- [ ] `database/` directory exists
- [ ] `k8s/` directory exists
- [ ] `architecture/` directory exists
- [ ] `docs/` directory exists
- [ ] `screenshots/` directory exists
- [ ] `Dockerfile` exists
- [ ] `docker-compose.yml` exists
- [ ] `kind-config.yaml` exists
- [ ] `grocery-backup.sql` exists
- [ ] `.gitignore` exists
- [ ] `README.md` exists
- [ ] `LICENSE` exists

## Configuration

- [ ] Environment variables are defined correctly
- [ ] Database configuration matches the application configuration
- [ ] No required environment variable is missing
- [ ] `DB_HOST` is provided through the Kubernetes ConfigMap
- [ ] `DB_NAME` is provided through the Kubernetes ConfigMap
- [ ] `DB_USER` is provided through the Kubernetes Secret
- [ ] `DB_PASSWORD` is provided through the Kubernetes Secret
- [ ] ConfigMap contains only non-sensitive configuration
- [ ] Kubernetes Secret contains sensitive database credentials
- [ ] Sensitive values are not hard-coded in application source code
- [ ] Real secret values are not committed to Git
- [ ] Sanitized Kubernetes Secret template contains placeholders only
- [ ] `.env` and `.env.*` files containing real secrets are excluded from Git
---

# 2. Docker Validation

## Docker Engine

Verify that Docker is installed and running:

```bash
docker --version
docker info
```

- [ ] Docker command works
- [ ] Docker daemon is running
- [ ] No Docker daemon connectivity errors

## Docker Compose

Verify Docker Compose version:

```bash
docker compose version
```

- [ ] Docker Compose is available
- [ ] Compose configuration is valid

Validate the Compose configuration:

```bash
docker compose config
```

- [ ] Configuration is rendered successfully
- [ ] No YAML or configuration errors are reported

---

# 3. Docker Image Validation

Build the application image:

```bash
docker compose build
```

- [ ] Web image builds successfully
- [ ] No Dockerfile errors
- [ ] Required PHP dependencies are available
- [ ] Apache configuration is valid

Check images:

```bash
docker images
```

- [ ] Expected application image exists
- [ ] Expected MySQL image exists

---

# 4. Docker Container Validation

Start the application:

```bash
docker compose up -d
```

Check running containers:

```bash
docker compose ps
```

Expected services:
* `grocery-web`
* `grocery-db`

- [ ] Web container is running
- [ ] Database container is running
- [ ] Containers are not repeatedly restarting

Check container status:

```bash
docker ps
```

- [ ] Container status is Up
- [ ] No unexpected containers are running

---

# 5. Application Validation

Access the application via browser: `http://localhost:8080`

- [ ] Application is reachable
- [ ] Apache serves the application
- [ ] PHP pages load correctly
- [ ] Static resources load correctly
- [ ] Login functionality works
- [ ] Product pages load correctly
- [ ] Application does not return unexpected HTTP 5xx errors

HTTP Validation:

```bash
curl -I http://localhost:8080
```

- [ ] HTTP request reaches the application
- [ ] Server returns an expected HTTP response

---

# 6. Database Validation

Check the database container:

```bash
docker compose ps grocery-db
```

- [ ] MySQL container is running
- [ ] MySQL is not restarting continuously

Check database logs:

```bash
docker compose logs grocery-db
```

- [ ] MySQL starts successfully
- [ ] No fatal database errors are present
- [ ] Database initialization completes successfully

## Database Connectivity

Verify that the application can communicate with MySQL through the Docker network:

```bash
docker network ls
```

- [ ] Application network exists

Inspect the network:

```bash
docker network inspect <network-name>
```

- [ ] `grocery-web` is connected
- [ ] `grocery-db` is connected

## Database Data

Verify database existence and initial data:

- [ ] Database `grocery` exists
- [ ] Required tables exist
- [ ] Initial SQL data is available
- [ ] Application can read database data
- [ ] Application can perform required database operations

---

# 7. Docker Networking Validation

Verify container communication:

```bash
docker network ls
```

- [ ] Docker network exists
- [ ] Web and database containers belong to the same application network

Verify database service resolution from the application container:

```text
grocery-web ──► Docker Network ──► grocery-db
```

- [ ] Database hostname/service name resolves correctly
- [ ] Application does not depend on a hard-coded container IP
- [ ] Web container can reach MySQL
- [ ] MySQL is not unnecessarily exposed to the public network

---

# 8. Port Mapping Validation

Expected Docker port mappings:
* **Application:** Host Port `8080` → Container Port `80`
* **Database:** Host Port `3307` → Container Port `3306`

Check published ports:

```bash
docker compose ps
```

- [ ] Port 8080 maps to Apache port 80
- [ ] Port 3307 maps to MySQL port 3306
- [ ] No unexpected port conflicts exist

---

# 9. Storage Validation

## Application Bind Mount
Expected mapping: `./app → /var/www/html`

- [ ] Application files are available inside the web container
- [ ] Local code changes are reflected inside the container when using the configured bind mount

## MySQL Named Volume
Expected mapping: `mysql-data → /var/lib/mysql`

Check volumes:

```bash
docker volume ls
```

- [ ] MySQL named volume exists
- [ ] MySQL data directory uses the expected volume

Inspect the volume:

```bash
docker volume inspect mysql-data
```

- [ ] Volume is correctly mounted
- [ ] Database data is stored outside the container lifecycle

---

# 10. Persistence Validation

## Container Restart Test

Restart the database:

```bash
docker compose restart grocery-db
```

- [ ] MySQL restarts successfully
- [ ] Database remains available
- [ ] Existing data is preserved

## Container Recreation Test

Recreate the services:

```bash
docker compose down
docker compose up -d
```

- [ ] Containers are recreated successfully
- [ ] MySQL starts successfully
- [ ] Existing database data remains available
- [ ] Application reconnects to the database

*Note: Do not use `docker compose down -v` during this test.*

---

# 11. Kubernetes Validation

Verify that the Kind cluster is available:

```bash
kind get clusters
```

- [ ] Expected Kind cluster exists

Verify Kubernetes connectivity:

```bash
kubectl cluster-info
kubectl get nodes 
```

- [ ] Kubernetes API server is reachable
- [ ] Kind node is Ready

---

# 12. Kubernetes Namespace Validation

```bash
kubectl get namespaces 
```

- [ ] Application namespace exists if a dedicated namespace is configured
- [ ] Resources are deployed in the expected namespace

---

# 13. Kubernetes Application Validation

Check application Pods:

```bash
kubectl get pods -n grocery
```

Expected workload: `grocery-app` Pod 1, `grocery-app` Pod 2

- [ ] Application Pods exist
- [ ] Desired number of replicas is running
- [ ] Pods are in Running state
- [ ] Pods are Ready
- [ ] Pods are not repeatedly restarting

Detailed validation:

```bash
kubectl describe pod <pod-name> -n grocery
```

- [ ] No image pull errors
- [ ] No scheduling errors
- [ ] No failed mounts
- [ ] Readiness/liveness checks behave as expected if configured

---

# 14. Kubernetes Service Validation

Check Services:

```bash
kubectl get svc -n grocery
```

Expected services: `grocery-app-service`, `mysql-service`

- [ ] Application Service exists
- [ ] MySQL Service exists
- [ ] Correct Service type is configured
- [ ] Application Service exposes the expected port
- [ ] MySQL Service exposes port 3306

Check Service endpoints:

```bash
kubectl get endpointslice -n grocery
```

- [ ] Application Service has healthy endpoints
- [ ] MySQL Service points to the database workload

---

# 15. Kubernetes Ingress Validation

Check the Ingress:

```bash
kubectl get ingress -n grocery
```

- [ ] Ingress resource exists
- [ ] Host is configured correctly (`grocery.local`)
- [ ] Path `/` is configured correctly
- [ ] Ingress routes to `grocery-app-service`
- [ ] Expected backend port is configured

Expected request flow:

```text
Browser ──► grocery.local ──► NGINX Ingress Controller ──► grocery-app-service ──► Application Pods
```

---

# 16. Local DNS / Hosts Validation

Verify local hostname configuration (`/etc/hosts` or `C:\Windows\System32\drivers\etc\hosts`):

- [ ] `grocery.local` points to `127.0.0.1`
- [ ] Browser can resolve `grocery.local`
- [ ] No conflicting hosts entry exists

Test resolution:

```bash
curl -I http://grocery.local:8081
```

---

# 17. Kubernetes ConfigMap Validation

```bash
kubectl get configmap -n grocery
```

- [ ] Required ConfigMap exists
- [ ] Non-sensitive configuration is stored in ConfigMap
- [ ] Application Pods receive the expected configuration

Inspect details:

```bash
kubectl describe configmap <configmap-name>
```

- [ ] Configuration keys are present
- [ ] No sensitive credentials are stored in the ConfigMap

---

# 18. Kubernetes Secret Validation

```bash
kubectl get secrets -n grocery
```

- [ ] Required Secret exists
- [ ] Sensitive configuration is stored using a Kubernetes Secret
- [ ] Application workload references the Secret correctly

Inspect metadata:

```bash
kubectl describe secret <secret-name>
```

- [ ] Secret exists
- [ ] Required keys are present
- [ ] Secret is referenced by the correct workload

---

# 19. Kubernetes Database Validation

Check the MySQL workload:

```bash
kubectl get statefulset -n grocery
kubectl get pods -n grocery
```

Expected workload: `mysql-0`

- [ ] MySQL StatefulSet exists
- [ ] `mysql-0` Pod exists
- [ ] MySQL Pod is Running and Ready

Check MySQL Service:

```bash
kubectl get svc mysql-service -n grocery
```

- [ ] Service exists and targets the MySQL workload
- [ ] Port 3306 is configured correctly

---

# 20. Kubernetes Persistent Storage Validation

Check PersistentVolumeClaims:

```bash
kubectl get pvc -n grocery
```

- [ ] PVC exists and is Bound
- [ ] Expected storage capacity is available
- [ ] PVC is attached to the MySQL workload (`mysql-0`)

---

# 21. Kubernetes Database Persistence Test

Delete the MySQL Pod to test storage persistence:

```bash
kubectl delete pod mysql-0 -n grocery 
```

Then verify:

```bash
kubectl get pods -n grocery
```

- [ ] Kubernetes recreates `mysql-0`
- [ ] MySQL becomes Running and Ready
- [ ] Existing database data remains available
- [ ] PVC remains attached
- [ ] Application reconnects to MySQL

---

# 22. Application Self-Healing Validation

Delete an application Pod to test self-healing:

```bash
kubectl delete pod <application-pod-name> -n grocery
```

Watch status:

```bash
kubectl get pods -n grocery -w
```

- [ ] Deleted Pod is recreated automatically
- [ ] Desired replica count is restored
- [ ] New Pod becomes Ready
- [ ] Application remains available through the Service

---

# 23. Application Load Balancing Validation

Check application replicas:

```bash
kubectl get pods -n grocery -o wide
```

- [ ] Multiple application replicas are running
- [ ] Pods have healthy status

Verify Service endpoint distribution:

```bash
kubectl describe svc grocery-app-service -n grocery 
```

- [ ] Service selects the application Pods
- [ ] Multiple healthy endpoints are available

---

# 24. End-to-End Kubernetes Validation

Access via browser: `http://grocery.local:8081`

Request Flow Check:
`Browser` ──► `grocery.local` ──► `NGINX Ingress` ──► `grocery-app-service` ──► `App Pod` ──► `mysql-service` ──► `mysql-0` ──► `PVC`

- [ ] Browser reaches the application
- [ ] Ingress routes correctly
- [ ] Service routes traffic to application Pods
- [ ] Application connects to MySQL successfully
- [ ] Database queries execute properly
- [ ] Application data displays correctly

---

# 25. Logs Validation

Docker logs:

```bash
docker compose logs
```

- [ ] No critical application errors
- [ ] No database initialization failures

Kubernetes logs:

```bash
kubectl logs -l app=grocery-app -n grocery 
kubectl logs mysql-0 -n grocery 
```

- [ ] Application logs are accessible
- [ ] Database logs show healthy startup

---

# 26. Resource Health Validation

Check Kubernetes resources:

```bash
kubectl get all -n grocery
```

- [ ] Pods, Services, Deployments, and StatefulSet are Healthy

Check recent cluster events:

```bash
kubectl get events -n grocery --sort-by=.lastTimestamp
```

- [ ] No unresolved warning events (e.g., ImagePullBackOff, FailedMount)

---

# 27. Security Validation

- [ ] Secrets are not committed to Git
- [ ] Database credentials are not hard-coded
- [ ] Kubernetes Secret is used for sensitive configuration
- [ ] ConfigMap contains only non-sensitive configuration
- [ ] MySQL is not unnecessarily exposed externally

---

# 28. Cleanup Validation

Docker environment:

```bash
docker compose down
```

- [ ] Containers stop successfully
- [ ] Network is cleaned up

Kubernetes environment:

```bash
kubectl delete -f k8s/
```

- [ ] Kubernetes resources are removed successfully

---

# 29. Final Acceptance Checklist

- [ ] **Docker:** Engine active, Compose valid, containers running, app reachable on `:8080`, DB persistent.
- [ ] **Kubernetes:** Cluster healthy, multi-pod replicas running, Ingress routing active, ConfigMap/Secret linked, StatefulSet DB persistent upon Pod deletion, self-healing verified.

---

# 30. Validation Completion Criteria

The Dockerized Grocery Store is considered validated for the current production-style local lab scope when:

- [ ] The application starts successfully.
- [ ] The application can communicate with the database.
- [ ] Database initialization works correctly.
- [ ] Application and database persistence work as designed.
- [ ] Kubernetes resources reach their expected healthy states.
- [ ] Ingress routes traffic successfully.
- [ ] Application Pods recover after failure.
- [ ] Database data survives Pod recreation.
- [ ] Configuration and secrets are separated appropriately.
- [ ] No unresolved critical errors remain.

---

# 31. Validation Result

| Environment Component | Status | Verified Date |
| :--- | :--- | :--- |
| **Docker Compose** | [ ] PASS / [ ] FAIL | |
| **Kubernetes / Kind** | [ ] PASS / [ ] FAIL | |
| **Application Layer** | [ ] PASS / [ ] FAIL | |
| **Database & Persistence** | [ ] PASS / [ ] FAIL | |
| **Networking & Ingress** | [ ] PASS / [ ] FAIL | |
| **Security Configuration**| [ ] PASS / [ ] FAIL | |
| **OVERALL STATUS** | **[ ] PASS** | |

### Notes
*Add validation notes, known limitations, or unresolved issues here.*

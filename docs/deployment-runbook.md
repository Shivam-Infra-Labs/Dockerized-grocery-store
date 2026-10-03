# Production-Style Deployment Runbook
## Dockerized Grocery Store — Docker + Kubernetes + MySQL + Ingress NGINX

> **Project:** Dockerized Grocery Store  
> **Deployment Platform:** Kubernetes (Kind)  
> **Container Runtime:** Docker / containerd  
> **Application:** PHP 8.2 + Apache  
> **Database:** MySQL 8.0  
> **Ingress:** NGINX Ingress Controller  
> **Environment:** Local Kubernetes Production-Style Lab  
> **Namespace:** `grocery`

---

## 1. Purpose

This runbook provides a repeatable, production-style deployment procedure for deploying the Dockerized Grocery Store application from source code to a Kubernetes cluster.

The deployment covers:

- Docker image build & validation
- Kind Kubernetes cluster creation
- Local image loading into Kind
- Kubernetes namespace creation
- ConfigMap and Secret configuration
- MySQL StatefulSet deployment & Persistent storage
- Database initialization and restore
- Application Deployment & Replicas scaling
- Health probes & Kubernetes Service endpoints
- NGINX Ingress Controller & Host-based routing
- End-to-end application & Database persistence verification
- Failure recovery testing, Troubleshooting, and Rollback

---

## 2. Architecture

### 2.1 Docker Architecture

    Source Code ──► Dockerfile ──► Docker Image ──► Docker Container (PHP 8.2 + Apache)

### 2.2 Kubernetes Architecture

                                     Browser
                                        │
                                        │ HTTP
                                        ▼
                                  grocery.local
                                        │
                                        ▼
                             NGINX Ingress Controller
                                        │
                                        ▼
                               grocery-app-service
                                        │
                             ┌──────────┴──────────┐
                             ▼                     ▼
                      grocery-app Pod       grocery-app Pod
                             │                     │
                             └──────────┬──────────┘
                                        │
                                  mysql-service
                                        │
                                        ▼
                                     mysql-0
                                        │
                                        ▼
                                 PersistentVolume

---

## 3. Repository Structure

Expected project structure:

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

---

## 4. Prerequisites

Verify tool installations before starting:

    docker --version
    kubectl version --client
    kind version
    git --version
    curl --version

Expected result: All CLI binaries are accessible and returning valid versions.

---

## 5. Pre-Deployment Checks

Verify no conflicting Kind clusters exist:

    kind get clusters
    docker ps

Check host port availability (`8081`, `8443`):

    sudo ss -ltnp | grep -E ':8081|:8443'

If ports are occupied by a local Web Server (e.g., Apache/NGINX), stop the service:

    sudo systemctl stop apache2
    sudo ss -ltnp | grep -E ':8081|:8443'

---

## 6. Kind Cluster Configuration

Create `kind-config.yaml`:

    kind: Cluster
    apiVersion: kind.x-k8s.io/v1alpha4
    nodes:
      - role: control-plane
        extraPortMappings:
          - containerPort: 80
            hostPort: 8081
            protocol: TCP
          - containerPort: 443
            hostPort: 8443
            protocol: TCP

Verify:

    cat kind-config.yaml

---

## 7. Create Kubernetes Cluster

Provision the Kind cluster:

    kind create cluster --name grocery-cluster --config kind-config.yaml

Verify cluster state, node status, and system pods:

    kind get clusters
    kubectl get nodes
    kubectl get pods -A

Expected output: Node `grocery-cluster-control-plane` status is `Ready`.

---

## 8. Build Application Image

Build the local Docker image:

    docker compose build --no-cache

Verify local image availability:

    docker images | grep dockerized-grocery-store-web

---

## 9. Load Image into Kind

Sideload the local Docker image into the Kind control-plane container:

    kind load docker-image dockerized-grocery-store-web:latest --name grocery-cluster

Verify image loaded into containerd inside Kind:

    docker exec grocery-cluster-control-plane crictl images | grep dockerized-grocery-store-web\

---

## 10. Create Application Namespace

Apply namespace configuration:

    kubectl apply -f k8s/namespace.yaml

Verify:

    kubectl get namespace grocery

---

## 11. Deploy Configuration

### 11.1 ConfigMap
    kubectl apply -f k8s/config/configmap.yaml
    kubectl get configmap -n grocery
    kubectl describe configmap grocery-config -n grocery

### 11.2 Secret
    kubectl apply -f k8s/config/secret.yaml
    kubectl get secret -n grocery
    kubectl describe secret grocery-secret -n grocery

---

## 12. Deploy MySQL Service

Apply database service:

    kubectl apply -f k8s/database/service.yaml
    kubectl get svc -n grocery

---

## 13. Deploy MySQL StatefulSet

Deploy database workload:

    kubectl apply -f k8s/database/statefulset.yaml

Watch deployment rollout:

    kubectl get pods -n grocery -w

Wait until `mysql-0` is `1/1 Running`.

---

## 14. Verify MySQL StatefulSet & Storage

Verify StatefulSet, PVC, and Volume status:

    kubectl get statefulset -n grocery
    kubectl get pvc -n grocery
    kubectl describe pvc -n grocery

Expected output: PVC status is `Bound`.

---

## 15. Verify Database Connectivity

Verify database connection from inside the cluster:

    kubectl exec -it -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery -e "SELECT 1;"

---

## 16. Restore Application Database

Restore database backup into MySQL StatefulSet:

    kubectl exec -i -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery < grocery-backup.sql

---

## 17. Verify Database Schema

Validate tables created successfully:

    kubectl exec -it -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery -e "SHOW TABLES;"

---

## 18. Verify Application Data

Confirm database record seeding:

    kubectl exec -it -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery -e "SELECT COUNT(*) AS product_count FROM product;"

Expected output: `product_count = 28`

---

## 19. Deploy Grocery Application

Apply application deployment manifest:

    kubectl apply -f k8s/app/deployment.yaml
    kubectl get deployment -n grocery

---

## 20. Verify Application Pods

Check running application pods:

    kubectl get pods -n grocery -l app=grocery-app -o wide

---

## 21. Verify Application Replicas

Check replica rollout status:

    kubectl get deployment grocery-app -n grocery

---

## 22. Verify Application Configuration

Ensure application environment variables point to `mysql-service`:

    kubectl exec -n grocery deployment/grocery-app -- env | grep '^DB_'

Expected: `DB_HOST=mysql-service`

---

## 23. Verify Application Logs

Check application runtime logs:

   kubectl logs -n grocery deployment/grocery-app


---

## 24. Deploy Application Service

Apply ClusterIP Service:

    kubectl apply -f k8s/app/service.yaml
    kubectl get svc -n grocery

---

## 25. Verify Service Endpoints

Verify Service Endpoint target discovery:

    kubectl get endpointslice -n grocery
    kubectl describe svc grocery-app-service -n grocery

---

## 26. Local Application Test

Test application Service directly using port-forward:

    kubectl port-forward -n grocery service/grocery-app-service 8080:80

In a separate terminal, verify:

    curl -I http://localhost:8080

---

## 27. Deploy NGINX Ingress Controller

Deploy Kind-compatible NGINX Ingress Controller:

    kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/main/deploy/static/provider/kind/deploy.yaml
    kubectl get pods -n ingress-nginx

---

## 28. Verify NGINX Controller

Wait for controller pod readiness:

    kubectl wait --namespace ingress-nginx \
      --for=condition=ready pod \
      --selector=app.kubernetes.io/component=controller \
      --timeout=120s

    kubectl get pods -n ingress-nginx

---

## 29. Deploy Grocery Ingress

Apply Ingress routing resource:

    kubectl apply -f k8s/ingress/ingress.yaml
    kubectl get ingress -n grocery
    kubectl describe ingress grocery-ingress -n grocery

---

## 30. Configure Local DNS

Add host mapping to `/etc/hosts`:

    echo "127.0.0.1 grocery.local" | sudo tee -a /etc/hosts
    grep grocery.local /etc/hosts

---

## 31. Verify Ingress Routing

Test host-based routing:

    curl -I -H "Host: grocery.local" http://127.0.0.1:8081/

Or access in browser: `http://grocery.local:8081/`

---

## 32. Verify HTTP Response

Validate HTTP header response:

    curl -I -H "Host: grocery.local" http://127.0.0.1:8081/

Expected output: `HTTP/1.1 200 OK`

---

## 33. End-to-End Verification

    Browser ──► grocery.local (127.0.0.1:8081) ──► NGINX Ingress Controller ──► grocery-ingress ──► grocery-app-service ──► grocery-app Pods ──► mysql-service ──► mysql-0 (Persistent Volume)

---

## 34. Production-Style Health Verification

Run complete resource status check:

    kubectl get nodes
    kubectl get pods -n grocery
    kubectl get svc -n grocery
    kubectl get pvc -n grocery
    kubectl get ingress -n grocery
    kubectl get endpointslice -n grocery

---

## 35. Application Failure Test

Delete one application pod to verify automatic recreation:

    kubectl delete pod $(kubectl get pod -n grocery -l app=grocery-app -o jsonpath='{.items[0].metadata.name}') -n grocery
    kubectl get pods -n grocery -w
    kubectl get deployment grocery-app -n grocery

---

## 36. Database Persistence Test

Verify data persistence across database pod recreations:

    # Check initial count
    kubectl exec -it -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery -e "SELECT COUNT(*) AS product_count FROM product;"

    # Delete database pod
    kubectl delete pod mysql-0 -n grocery
    kubectl wait --namespace grocery --for=condition=ready pod/mysql-0 --timeout=120s

    # Verify count after pod restart
    kubectl exec -it -n grocery mysql-0 -- mysql -h 127.0.0.1 -uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery -e "SELECT COUNT(*) AS product_count FROM product;"

Expected output: `product_count = 28`

---

## 37. Final Deployment Verification

    kubectl get nodes
    kubectl get pods -n grocery -o wide
    kubectl get svc -n grocery
    kubectl get pvc -n grocery
    kubectl get ingress -n grocery
    kubectl get endpointslice -n grocery

    curl -I -H "Host: grocery.local" http://127.0.0.1:8081/

Expected result: `HTTP/1.1 200 OK`

---

## 38. Rollback Procedures

### 38.1 Rollback Application Deployment
    kubectl rollout history deployment/grocery-app -n grocery
    kubectl rollout undo deployment/grocery-app -n grocery
    kubectl rollout status deployment/grocery-app -n grocery

### 38.2 Restart Application Workload
    kubectl rollout restart deployment/grocery-app -n grocery
    kubectl rollout status deployment/grocery-app -n grocery

---

## 39. Safe Cleanup

Delete application namespace resources:

    kubectl delete namespace grocery
    kubectl get namespace grocery

Delete the Kind Kubernetes cluster:

    kind delete cluster --name grocery-cluster
    kind get clusters

---

## 40. Deployment Success Criteria

- [ ] Docker image builds successfully and is loaded into Kind.
- [ ] Kind cluster nodes are in `Ready` state.
- [ ] Namespace, ConfigMap, and Secret exist in cluster.
- [ ] MySQL StatefulSet PVC status is `Bound`.
- [ ] Database schema & seeded records exist.
- [ ] Application Deployment replicas are active and healthy.
- [ ] Service target endpoints are successfully populated.
- [ ] NGINX Ingress routes `(http://grocery.local:8081)` with `200 OK`.
- [ ] Application auto-heals upon Pod termination.
- [ ] Database state persists across `mysql-0` deletion.

---

## 41. Operational Verification Commands

    # Health checks
    kubectl get nodes
    kubectl get pods -n grocery
    kubectl get svc -n grocery
    kubectl get pvc -n grocery
    kubectl get ingress -n grocery
    kubectl get endpointslice -n grocery

    # Application logs
    kubectl logs -n grocery deployment/grocery-app

    # Database logs
    kubectl logs -n grocery mysql-0

    # Ingress logs
    kubectl logs -n ingress-nginx -l app.kubernetes.io/component=controller

---

## 42. Troubleshooting Reference

Refer to `docs/troubleshooting.md` for extended failure trees.

    Pod Pending ──► kubectl describe pod ──► Check resource allocation / PVC binding
    Pod CrashLoop ──► kubectl logs ──► Check DB connection credentials / environment variables
    ImagePullBackOff ──► Check image name/tag ──► Check kind load ──► Check imagePullPolicy
    Service no endpoints ──► Check Pod labels ──► Check Service selectors ──► Check EndpointSlice
    Ingress 503 ──► Check Ingress ──► Check Service ──► Check Endpoints ──► Check NGINX Controller logs

---

## 43. Deployment Evidence

Recommended screenshots for repository evidence:

    screenshots/
├── application.png
├── docker-build.png
├── docker-compose.png
├── cluster.png
├── pods.png
├── database.png
├── storage.png
├── service.png
├── ingress.png
├── deployment-success.png
└── self-healing.png
---

## 44. Deployment Flow Summary

    Source Code ──► Dockerfile ──► Docker Image ──► Kind Cluster ──► Namespace ──► ConfigMap/Secret ──► MySQL StatefulSet ──► PVC ──► DB Restore ──► App Deployment ──► App Service ──► NGINX Ingress ──► grocery.local ──► Browser

---

## 45. Final Status Report

## Validation Status

| Component | Status |
|---|---|
| Docker Build | [ ] Pending |
| Kind Kubernetes Cluster | [ ] Pending |
| ConfigMap & Secret | [ ] Pending |
| MySQL StatefulSet & Storage | [ ] Pending |
| Application Deployment | [ ] Pending |
| NGINX Ingress Routing | [ ] Pending |
| Data Persistence & Self-Healing | [ ] Pending |

### Overall Status

**VALIDATION PENDING**

This runbook describes the validation procedure for the local Kubernetes deployment.

After executing the validation steps successfully, the project can be marked as:

**READY FOR LOCAL VALIDATION / DEMO**

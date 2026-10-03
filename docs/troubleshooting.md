# Troubleshooting Playbook

## Dockerized Grocery Store — Kubernetes / Kind

> A practical troubleshooting runbook for diagnosing and resolving common failures in the Dockerized Grocery Store Kubernetes deployment.

---

## 1. Troubleshooting Philosophy

When a Kubernetes deployment fails, do not randomly restart or recreate resources.

Follow this diagnostic flow:

Problem
   ↓
Identify the failing layer
   ↓
Check resource status
   ↓
Describe resource
   ↓
Check logs/events
   ↓
Check dependencies
   ↓
Apply targeted fix
   ↓
Verify

General troubleshooting order:

Pod
 ↓
Container
 ↓
Deployment
 ↓
Service
 ↓
EndpointSlice
 ↓
Ingress
 ↓
NGINX Controller
 ↓
Host / Port Mapping

---

## 2. Initial Cluster Health Check

Always begin with:

kubectl get nodes
kubectl get pods -A

Expected:
- Node STATUS = Ready
- System pods = Running

Check detailed node information:
kubectl get nodes -o wide

Check cluster information:
kubectl cluster-info

Check current Kubernetes context:
kubectl config current-context

For Kind:
kind get clusters

Expected output:
grocery-cluster

---

## 3. Pod Troubleshooting

### 3.1 Pod Pending

Symptom: STATUS: Pending

Check Pods & Describe:
kubectl get pods -n grocery
kubectl describe pod <pod-name> -n grocery

Check events:
kubectl get events -n grocery --sort-by=.lastTimestamp

Common causes:
- Insufficient resources
- PVC not available
- Scheduling problem
- Missing Secret / ConfigMap
- Node problem
- Image-related issue

Diagnostic command:
kubectl describe pod <pod-name> -n grocery

Look at the Events: section at the bottom — it usually contains the root cause.

---

## 4. Pod ContainerCreating

Symptom: STATUS: ContainerCreating (Example: mysql-0 0/1 ContainerCreating)

Diagnostic steps:
kubectl describe pod mysql-0 -n grocery
kubectl get events -n grocery --sort-by=.lastTimestamp
kubectl get pvc -n grocery
kubectl get storageclass

For Kind, check local path storage provisioner:
kubectl get pods -n local-path-storage

Expected:
local-path-provisioner   Running

If the pod eventually becomes 1/1 Running, then the temporary ContainerCreating state was normal startup behavior.

---

## 5. Pod CrashLoopBackOff

Symptom: STATUS: CrashLoopBackOff

Immediately check logs:
kubectl logs <pod-name> -n grocery

If the pod restarted, check previous container logs:
kubectl logs <pod-name> -n grocery --previous
kubectl describe pod <pod-name> -n grocery

Check restart count:
kubectl get pods -n grocery

Common causes:
- Application configuration error
- Database connection failure
- Missing environment variable
- Wrong Secret / ConfigMap
- PHP/Apache startup error
- Application code error
- Incorrect container command
- Permission issue

Verification:
After fixing:
kubectl get pods -n grocery -w

Expected:
READY   1/1
STATUS  Running

---

## 6. ImagePullBackOff / ErrImagePull

Symptom: ImagePullBackOff or ErrImagePull

Check Pod details:
kubectl describe pod <pod-name> -n grocery
Look for: Failed to pull image

Check the image configured in Deployment:
kubectl get deployment grocery-app -n grocery -o yaml

Check local Docker image:
docker images

For the Kind cluster, load the image manually:
kind load docker-image dockerized-grocery-store-web:latest --name grocery-cluster

Verify image exists inside the Kind node:
docker exec grocery-cluster-control-plane crictl images | grep dockerized-grocery-store-web

Restart Deployment if required:
kubectl rollout restart deployment grocery-app -n grocery
kubectl get pods -n grocery

---

## 7. Kind Image Loading Troubleshooting

For locally built images, Kind does not automatically use the host Docker image.

Build & verify image:
docker compose build
docker images | grep dockerized-grocery-store-web

Load into Kind & verify:
kind load docker-image dockerized-grocery-store-web:latest --name grocery-cluster
docker exec grocery-cluster-control-plane crictl images | grep dockerized-grocery-store-web

Then deploy & verify:
kubectl apply -f k8s/app/deployment.yaml
kubectl get pods -n grocery

---

## 8. Deployment Not Ready

Check deployment status:
kubectl get deployment -n grocery
kubectl describe deployment grocery-app -n grocery

Check ReplicaSet & Pods:
kubectl get rs -n grocery
kubectl get pods -n grocery -o wide

Check rollout status:
kubectl rollout status deployment/grocery-app -n grocery

Expected:
deployment "grocery-app" successfully rolled out

---

## 9. Service Troubleshooting

### 9.1 Service Does Not Exist

Check:
kubectl get svc -n grocery

If grocery-app-service is not found, apply the Service manifest:
kubectl apply -f k8s/app/service.yaml
kubectl get svc -n grocery

---

## 10. Service Has No Endpoints

This is one of the most important Kubernetes troubleshooting checks.

Check Service & EndpointSlice:
kubectl get svc grocery-app-service -n grocery
kubectl get endpointslice -n grocery

Expected:
grocery-app-service-xxxxx
PORTS   80
ENDPOINTS   10.244.x.x,10.244.x.x

Describe Service & check labels:
kubectl describe svc grocery-app-service -n grocery
kubectl get pods -n grocery --show-labels
kubectl get svc grocery-app-service -n grocery -o yaml

The Service selector must match the Pod labels.

Example:
selector:
  app: grocery-app

Pod must contain:
labels:
  app: grocery-app

Failure Chain:
Service → No matching Pods → No EndpointSlice endpoints → Ingress returns 503

---

## 11. EndpointSlice Troubleshooting

Preferred command on modern Kubernetes:
kubectl get endpointslice -n grocery
kubectl describe endpointslice <endpoint-slice-name> -n grocery

Check ENDPOINTS and PORTS. Expected endpoints should look like: 10.244.0.7, 10.244.0.8.

If the EndpointSlice has no application endpoints:
Check Pod labels
        ↓
Check Service selector
        ↓
Check Pod Ready status

---

## 12. Database Troubleshooting

Check MySQL Pod health:
kubectl get pods -n grocery
kubectl logs mysql-0 -n grocery

Expected: mysql-0 1/1 Running

Check MySQL Service & EndpointSlice:
kubectl get svc mysql-service -n grocery
kubectl get endpointslice -n grocery

---

## 13. Test MySQL Connectivity

Execute MySQL client inside the database Pod:
kubectl exec -it -n grocery mysql-0 -- \
mysql --protocol=tcp -h127.0.0.1 \
-uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery \
-e "SHOW TABLES;"

Expected tables: admin, category, feedback, ord, order_items, payment, product, user

Check product count:
kubectl exec -it -n grocery mysql-0 -- \
mysql --protocol=tcp -h127.0.0.1 \
-uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery \
-e "SELECT COUNT(*) AS product_count FROM product;"

Expected output: 28

---

## 14. MySQL Backup Troubleshooting

A normal dump may show Access denied; you need (at least one of) the PROCESS privilege(s) when dumping tablespaces.

For the application database user, use --no-tablespaces:
mysqldump \
--protocol=tcp \
-h127.0.0.1 \
-uYOUR_DB_USER \
-pYOUR_DB_PASSWORD \
--no-tablespaces \
grocery > grocery-backup.sql

Verify dump file:
ls -lh grocery-backup.sql
grep -E '^CREATE TABLE|^INSERT INTO' grocery-backup.sql | head -20
grep -c "INSERT INTO \`product\`" grocery-backup.sql

---

## 15. MySQL Restore Troubleshooting

Restore backup into MySQL:
kubectl exec -i -n grocery mysql-0 -- \
mysql --protocol=tcp -h127.0.0.1 \
-uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery \
< grocery-backup.sql

Verify restored data:
kubectl exec -it -n grocery mysql-0 -- \
mysql --protocol=tcp -h127.0.0.1 \
-uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery \
-e "SHOW TABLES;"

kubectl exec -it -n grocery mysql-0 -- \
mysql --protocol=tcp -h127.0.0.1 \
-uYOUR_DB_USER -pYOUR_DB_PASSWORD grocery \
-e "SELECT COUNT(*) FROM product;"

---

## 16. Ingress Troubleshooting

### 16.1 Ingress 503 Service Temporarily Unavailable

Symptom: 503 Service Temporarily Unavailable
Do NOT immediately reinstall NGINX. Follow this order:

Ingress → Service → EndpointSlice → Pods

Check Ingress:
kubectl get ingress -n grocery
kubectl describe ingress grocery-ingress -n grocery

Check Service & Endpoints:
kubectl get svc grocery-app-service -n grocery
kubectl get endpointslice -n grocery
kubectl get pods -n grocery -o wide

---

## 17. Ingress Backend Verification

The Ingress backend must reference the correct Service port and name:
backend:
  service:
    name: grocery-app-service
    port:
      number: 80

Verify configuration:
kubectl get ingress grocery-ingress -n grocery -o yaml

Expected details:
- Host: grocery.local
- Backend: grocery-app-service:80

---

## 18. Test Application Without Ingress

Before debugging NGINX, verify that the application Service itself works using port forwarding:

kubectl port-forward -n grocery svc/grocery-app-service 8080:80

From another terminal:
curl http://127.0.0.1:8080/

- If this works: Application → Service is working. Focus on Ingress.
- If this fails: Focus on fixing the Service/Application first.

---

## 19. NGINX Ingress Controller Troubleshooting

Check controller status:
kubectl get pods -n ingress-nginx

Expected: ingress-nginx-controller-xxxxx 1/1 Running

Wait for readiness:
kubectl wait --namespace ingress-nginx \
--for=condition=ready pod \
--selector=app.kubernetes.io/component=controller \
--timeout=120s

Check controller logs:
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller -f

---

## 20. Ingress Admission Webhook Error

Symptom: failed calling webhook validate.nginx.ingress.kubernetes.io or connect: connection refused

Diagnostic commands:
kubectl get pods -n ingress-nginx
kubectl get svc -n ingress-nginx
kubectl get endpointslice -n ingress-nginx
kubectl get jobs -n ingress-nginx

Wait for controller readiness and re-apply:
kubectl wait --namespace ingress-nginx \
--for=condition=ready pod \
--selector=app.kubernetes.io/component=controller \
--timeout=120s

kubectl apply -f k8s/ingress/ingress.yaml

Important: Do not troubleshoot the application when the Kubernetes API itself cannot validate the Ingress resource. First fix the Ingress Admission Webhook.

---

## 21. Ingress Hostname / /etc/hosts Troubleshooting

For local Kind development, add to /etc/hosts:
127.0.0.1 grocery.local

Check /etc/hosts and test DNS resolution:
cat /etc/hosts
getent hosts grocery.local

Expected output:
127.0.0.1 grocery.local

---

## 22. Host Header Testing

Test Ingress using custom Host headers:
curl -H "Host: grocery.local" http://127.0.0.1:8081/
# Or directly:
curl http://grocery.local:8081/

The Host header is required because the Ingress rule explicitly filters requests for grocery.local. Without it, NGINX will not route traffic to the application.

---

## 23. Kind Port Mapping Troubleshooting

For local browser access, Kind must publish ports from the Kubernetes node container to the host in kind-config.yaml:

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

Verify ports are not already occupied on the host:
sudo ss -ltnp | grep ':8081'
sudo ss -ltnp | grep ':80'
sudo ss -ltnp | grep ':443'

---

## 24. Host Port Already in Use

Example Kind error: failed to bind host port 0.0.0.0:80/tcp: address already in use

Find conflicting process:
sudo ss -ltnp | grep ':80'

If Apache/NGINX is running on the host:
- Option A (Stop host service):
  sudo systemctl stop apache2
  sudo ss -ltnp | grep ':80'
- Option B (Use alternative host port in kind-config.yaml):
  extraPortMappings:
    - containerPort: 80
      hostPort: 8081
      protocol: TCP

---

## 25. Kind Configuration Changes Require Cluster Recreation

Changing kind-config.yaml does NOT update an existing cluster. Recreate the cluster:

kind delete cluster --name grocery-cluster
kind get clusters

kind create cluster \
--name grocery-cluster \
--config kind-config.yaml

kubectl get nodes

Expected: grocery-cluster-control-plane Ready

---

## 26. Browser Works but Curl Fails

If http://grocery.local:8081 works in the browser but curl http://127.0.0.1:8081/ fails, test with the explicit host header:

curl -H "Host: grocery.local" http://127.0.0.1:8081/

---

## 27. Browser Shows 503

Follow this inspection sequence:
1. kubectl get ingress -n grocery
2. kubectl get svc grocery-app-service -n grocery
3. kubectl get endpointslice -n grocery
4. kubectl get pods -n grocery
5. kubectl logs -n ingress-nginx deployment/ingress-nginx-controller

Decision Tree:
503
 ↓
Does Service exist?
 ├── NO → Apply Service YAML
 └── YES
      ↓
Does EndpointSlice contain app endpoints?
 ├── NO → Check labels/selectors + Pod readiness
 └── YES
      ↓
Does application respond through Service?
 ├── NO → Troubleshoot application
 └── YES
      ↓
Check Ingress & NGINX logs

---

## 28. Deployment Rollout Problems

Check rollout status and history:
kubectl rollout status deployment/grocery-app -n grocery
kubectl rollout history deployment/grocery-app -n grocery

Restart deployment:
kubectl rollout restart deployment/grocery-app -n grocery
kubectl get pods -n grocery

---

## 29. Rollback

If a new deployment breaks:
kubectl rollout history deployment/grocery-app -n grocery
kubectl rollout undo deployment/grocery-app -n grocery
kubectl rollout status deployment/grocery-app -n grocery
kubectl get pods -n grocery

---

## 30. ConfigMap Troubleshooting

Check ConfigMaps:
kubectl get configmap -n grocery
kubectl describe configmap grocery-config -n grocery

Verify deployment reference:
kubectl get deployment grocery-app -n grocery -o yaml

Common problems: Wrong key, wrong value, wrong ConfigMap name, or created in the wrong namespace (ConfigMaps are namespace-scoped).

---

## 31. Secret Troubleshooting

Check Secrets:
kubectl get secret -n grocery
kubectl describe secret grocery-secret -n grocery

Verify deployment reference:
kubectl get deployment grocery-app -n grocery -o yaml

Note: Never expose Secret values in public repositories like GitHub.

---

## 32. Namespace Troubleshooting

Check namespace health:
kubectl get namespace
kubectl get namespace grocery

If missing, recreate:
kubectl apply -f k8s/namespace.yaml
kubectl get namespace grocery

---

## 33. Resource Exists in Another Namespace

Kubernetes resources are namespace-scoped. Always pass -n grocery:

kubectl get pods -n grocery

To search for misplaced resources across all namespaces:
kubectl get pods -A

---

## 34. YAML Validation

Validate manifest syntax before applying:

kubectl apply --dry-run=client -f k8s/namespace.yaml
kubectl apply --dry-run=client -f k8s/ingress/ingress.yaml
kubectl apply --dry-run=client -f k8s/app/deployment.yaml
kubectl apply --dry-run=client -f k8s/

---

## 35. Kubernetes Events

Check cluster events sorted by timestamp:

kubectl get events -n grocery --sort-by=.lastTimestamp
kubectl get events -A --sort-by=.lastTimestamp

Useful for debugging: Pending, ContainerCreating, FailedMount, FailedScheduling, ImagePullBackOff, CrashLoopBackOff, and Probe failures.

---

## 36. Application Logs

# Current logs
kubectl logs <pod-name> -n grocery

# Follow logs
kubectl logs -f <pod-name> -n grocery

# Previous container logs (after crash)
kubectl logs <pod-name> -n grocery --previous

# Specific container inside multi-container pod
kubectl logs <pod-name> -n grocery -c <container-name>

---

## 37. Exec Into Application Pod

Enter the running Pod:
kubectl exec -it <pod-name> -n grocery -- /bin/bash
# If bash is missing:
kubectl exec -it <pod-name> -n grocery -- /bin/sh

Inspect files & services inside container:
ls -la /var/www/html
ps aux | grep apache

---

## 38. Test Service From Inside Cluster

Run a temporary curl container inside the cluster:

kubectl run curl-test \
-n grocery \
--rm -it \
--image=curlimages/curl \
-- sh

Inside the container, test the service:
curl http://grocery-app-service
exit

---

## 39. Check DNS Inside Cluster

Run a temporary BusyBox container to check CoreDNS resolution:

kubectl run dns-test \
-n grocery \
--rm -it \
--image=busybox \
-- sh

Inside the container:
nslookup grocery-app-service
nslookup mysql-service
exit

---

## 40. Common Failure Decision Trees

### Pod Pending
Pod Pending → kubectl describe pod → Check Events → Scheduling / PVC / Resource issue

### Pod CrashLoopBackOff
CrashLoopBackOff → kubectl logs → kubectl logs --previous → kubectl describe pod → Fix code/config

### ImagePullBackOff
ImagePullBackOff → Check image name → docker images → kind load docker-image → Restart Deployment

### Service Has No Endpoints
Service has no endpoints → Check Pod labels → Check Service selector → Check Pod READY → Check EndpointSlice

### Ingress 503
Ingress 503 → Check Ingress → Check Service → Check EndpointSlice → Test Service directly → Check NGINX logs

### Ingress Webhook Error
Ingress webhook error → Check ingress-nginx Pod → Check admission Service → Check EndpointSlice → Wait for controller → Reapply Ingress

### Kind Port Binding Error
Kind create failed → address already in use → sudo ss -ltnp → Identify process → Stop service / Change hostPort → Recreate Kind cluster

---

## 41. Production-Style Diagnostic Checklist

Collect these outputs when reporting or documenting an incident:

kubectl get nodes -o wide
kubectl get pods -A
kubectl get pods -n grocery -o wide
kubectl get svc -n grocery
kubectl get endpointslice -n grocery
kubectl get ingress -n grocery
kubectl get deployment -n grocery
kubectl get events -n grocery --sort-by=.lastTimestamp

For Application Failures:
kubectl describe pod <pod-name> -n grocery
kubectl logs <pod-name> -n grocery

For Ingress Failures:
kubectl describe ingress grocery-ingress -n grocery
kubectl get svc grocery-app-service -n grocery
kubectl get endpointslice -n grocery
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller

---

## 42. Final Verification

Verify the entire end-to-end request path:

Browser 
   ↓ 
grocery.local 
   ↓ 
127.0.0.1 
   ↓ 
Kind Host Port 
   ↓ 
NGINX Ingress Controller 
   ↓ 
Ingress Rule 
   ↓ 
grocery-app-service 
   ↓ 
Grocery Application Pods 
   ↓ 
mysql-service 
   ↓ 
MySQL StatefulSet 
   ↓ 
Persistent Storage

Run final health checks:
kubectl get nodes
kubectl get pods -n grocery
kubectl get svc -n grocery
kubectl get endpointslice -n grocery
kubectl get ingress -n grocery

Test application URL:
curl -H "Host: grocery.local" http://127.0.0.1:8081/
Or open browser: http://grocery.local:8081

Expected result: Grocery Store application loads successfully.

---

## 43. Troubleshooting Golden Rules

1. Never guess the root cause.
2. Always check kubectl describe for resource-level failures.
3. Always check kubectl logs for container-level failures.
4. Always check EndpointSlice when a Service appears unhealthy.
5. Always verify Service selectors against Pod labels.
6. Test the application without Ingress before debugging Ingress.
7. Check NGINX logs when the backend is healthy but Ingress still fails.
8. For Kind, remember that host port mappings are configured during cluster creation.
9. After changing kind-config.yaml, recreate the Kind cluster.
10. Never commit real production credentials or Secret values to GitHub.
11. Prefer targeted fixes instead of deleting the entire cluster.
12. Verify every fix after applying it.
13. Keep the troubleshooting evidence when documenting an incident.
14. Follow the dependency chain instead of randomly restarting components.

---

## 44. Quick Command Reference

# Cluster Management
kubectl get nodes
kubectl cluster-info
kind get clusters

# Resource Status
kubectl get pods -A
kubectl get events -A --sort-by=.lastTimestamp

# Application Level
kubectl get pods -n grocery
kubectl get deployment -n grocery
kubectl rollout status deployment/grocery-app -n grocery

# Logging
kubectl logs <pod> -n grocery
kubectl logs <pod> -n grocery --previous

# Inspection
kubectl describe pod <pod> -n grocery
kubectl describe svc <service> -n grocery
kubectl describe ingress <ingress> -n grocery

# Service & Routing
kubectl get svc -n grocery
kubectl get endpointslice -n grocery

# Ingress
kubectl get ingress -n grocery
kubectl describe ingress grocery-ingress -n grocery

# NGINX Controller
kubectl get pods -n ingress-nginx
kubectl logs -n ingress-nginx deployment/ingress-nginx-controller

# Kind Local Image Loading
docker images
kind load docker-image dockerized-grocery-store-web:latest --name grocery-cluster

# Port Checking
sudo ss -ltnp | grep ':80'
sudo ss -ltnp | grep ':8081'

# Hosts & DNS Verification
cat /etc/hosts
getent hosts grocery.local

# HTTP Connectivity Test
curl -H "Host: grocery.local" http://127.0.0.1:8081/

---

## 45. Troubleshooting Summary

                    KUBERNETES TROUBLESHOOTING
                              │
                              ▼
                       kubectl get pods
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
           Pending       CrashLoopBackOff   ImagePullBackOff
              │               │               │
              ▼               ▼               ▼
       describe + events     logs         image + kind load
              │               │               │
              └───────────────┼───────────────┘
                              ▼
                       Service Problem
                              │
                              ▼
                     Check EndpointSlice
                              │
                              ▼
                       Check Pod Labels
                              │
                              ▼
                      Check Service Selector
                              │
                              ▼
                       Test Service Directly
                              │
                              ▼
                        Ingress Problem
                              │
                              ▼
                       Check Ingress Rule
                              │
                              ▼
                       Check NGINX Controller
                              │
                              ▼
                         Check NGINX Logs
                              │
                              ▼
                       Check Host / Ports
                              │
                              ▼
                         Final Verification

---

## End State

A successful deployment should satisfy all of the following:

- Kind Cluster: Running
- Node: Ready
- MySQL Pod: 1/1 Running
- Application Pods: 2/2 Running
- Application Service: Has endpoints
- Ingress Controller: 1/1 Running
- Ingress: grocery.local
- Host Resolution: grocery.local → 127.0.0.1
- HTTP Request: 200 OK / Application Response
- Browser: Grocery Store loads
- Database: 28 products available

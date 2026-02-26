# Flip Test Project

This project is a demonstration of a system that submits orders to a third-party provider and handles the asynchronous nature of the order processing.

## 1. How to Run Locally

This project is fully containerized with Docker.

### Prerequisites
- Docker
- Docker Compose

### Setup
1.  **Clone the repository.**
2.  **Build and Start Services:** Build the images and run the services in detached mode.
    ```bash
    docker-compose up -d --build
    ```
    This will start the following containers:
    - `flip_app`: The main Laravel application, including the web server (`php-fpm`) and the queue worker.
    - `flip_nginx`: Nginx web server to route requests to the `app` service.
    - `flip_postgres`: PostgreSQL database.
    - `flip_redis`: Redis for caching and queueing.

3.  **Run composer install:** Install required modules from composer.
    ```bash
    docker exec flip_app composer install
    ```

4.  **Run Database Migrations:** Apply the database schema to your Postgres container.
    ```bash
    docker exec flip_app php artisan migrate
    ```
5.  **Access the Application:** The application is now running and accessible at `http://localhost:8080`.

    You can check the health of the database connection at `http://localhost:8080/health`.

4.  **Start Queue worker:** Run the queue worker to process orders.
    ```bash
    docker exec flip_app php artisan queue:work
    ```

## 2. How the Async Worker Runs

The project uses a Laravel queue to process order submissions asynchronously.

- **Queue Driver:** Redis is used as the queue driver.
- **Worker Process:** A `php artisan queue:work` process runs continuously within the `app` container.

When an order is created via the API, a `SubmitOrderJob` is dispatched to the queue. The worker picks up this job and handles the entire lifecycle of submitting the order to the provider and polling for its status until it's completed or fails.

## 3. How to Trigger Provider Failures/Timeouts

The application includes a built-in provider simulator (`ProviderController`). You can control its behavior using environment variables in the `own_services/.env` file to test various scenarios.

**HTTP Errors:**
- `PROVIDER_SIM_SUBMIT_ERROR=true`: The provider's `submit` endpoint will return a `500 Internal Server Error`.
- `PROVIDER_SIM_STATUS_ERROR=true`: The provider's `status` endpoint will return a `500 Internal Server Error` on every poll.

**Timeouts:**
To simulate a timeout, the job's timeout (`timeout` property in `SubmitOrderJob.php`, currently 30s) must be shorter than the simulated delay.
- `PROVIDER_SIM_SUBMIT_TIMEOUT=true`: The provider's `submit` endpoint will delay for 12 seconds before responding.
- `PROVIDER_SIM_STATUS_TIMEOUT=true`: The provider's `status` endpoint will delay for 12 seconds before responding.

**Order Resolution Logic:**
- `PROVIDER_SIM_FORCE_FAILURE=true`: The order will eventually be marked as `FAILED` by the provider.
- `PROVIDER_SIM_FAILURE_REASON="Your reason here"`: Sets a custom failure reason when `PROVIDER_SIM_FORCE_FAILURE` is true.
- `PROVIDER_SIM_POLLS_TO_COMPLETE=5`: Change the number of status polls required before an order is marked `COMPLETED`. Defaults to 3.

After changing any of these `.env` variables, you may need to restart the queue worker in `flip_app` container for the changes to be picked up by the running worker.

a. ctrl-c to kill the worker
b. docker exec flip_app php artisan queue:work

## 4. Our Key Trade-offs

The current design makes certain trade-offs for the sake of simplicity and rapid development.

- **Single Container for Web & Worker:** For ease of use in a local development environment, both the `php-fpm` (web) process and the `queue:work` (worker) process run inside the same `app` container, managed manually.
    - **Pro:** Simplified setup, fewer containers to manage.
    - **Con:** This is not a scalable production architecture. In a real-world scenario, web and worker processes should be in separate, independently scalable services to prevent resource contention (e.g., a heavy job slowing down web requests).

- **Polling for Status vs. Webhooks:** The worker job (`SubmitOrderJob`) actively polls the provider's `/status` endpoint to get order updates.
    - **Pro:** Simple to implement, requires no public-facing endpoint on our side for the provider to call.
    - **Con:** Inefficient. It creates repetitive network traffic and introduces delays in receiving the final status. A more robust and efficient solution would be for the provider to push status updates to us via a webhook.

- **In-App Provider Simulation:** The third-party provider is simulated by a controller within the same Laravel application.
    - **Pro:** Extremely simple to run and test against. No need for another service or codebase.
    - **Con:** The simulation is tightly coupled to the application. The state of provider orders is stored in the application's own database (`provider_orders` table), which wouldn't be the case with a real external provider.

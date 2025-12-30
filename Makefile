COMPOSER_AUTH='{ "bitbucket-oauth": { "bitbucket.org": { "consumer-key": "$(BITBUCKET_CONSUMER_KEY)", "consumer-secret": "$(BITBUCKET_CONSUMER_SECRET)" } } }'

help:           ## Show this help.
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

bash:		## bash into console container
	@docker-compose exec \
		--env AWS_ACCESS_KEY_ID=$(AWS_ACCESS_KEY_ID) \
		--env AWS_SECRET_ACCESS_KEY=$(AWS_SECRET_ACCESS_KEY) \
		--env AWS_SESSION_TOKEN=$(AWS_SESSION_TOKEN) \
		app sh

build: up

build-nd:		## Build console, login and api - no-detached
	docker-compose up --build --remove-orphans

down:
	docker-compose down --rmi=local --remove-orphans --volumes

fix-permissions:		## Fix file permissions
	sudo chown -R $(whoami): .

logs:
	docker compose logs -f

stop-all:		## Stop all running containers 
	docker stop $$(docker ps -q)

up:
	docker compose up --build --remove-orphans -d

up-nd:
	docker-compose up --build --remove-orphans

install-dev:		## Package the PHP code for development
	docker-compose exec app sh -c "cd /app/php && composer install"

package:		## Package the PHP code for deployment
	docker-compose exec app sh -c "cd /app/php && composer run ci:deploy"

deploy: deploy-sandbox install-dev

deploy-sandbox:	## Deploy to sandbox environment with hotswap (faster but doesn't update Lambda code)
	@docker-compose exec \
		--env AWS_ACCESS_KEY_ID=$(AWS_ACCESS_KEY_ID) \
		--env AWS_SECRET_ACCESS_KEY=$(AWS_SECRET_ACCESS_KEY) \
		--env AWS_SESSION_TOKEN=$(AWS_SESSION_TOKEN) \
		app sh -c "cd /app && npm run deploy"

deploy-sandbox-full:	## Deploy to sandbox environment with full deployment (updates Lambda code)
	@docker-compose exec \
		--env AWS_ACCESS_KEY_ID=$(AWS_ACCESS_KEY_ID) \
		--env AWS_SECRET_ACCESS_KEY=$(AWS_SECRET_ACCESS_KEY) \
		--env AWS_SESSION_TOKEN=$(AWS_SESSION_TOKEN) \
		app sh -c "cd /app && npm run deploy:sandbox:full"

deploy-prod:	## Deploy to production environment
	@docker-compose exec \
		--env AWS_ACCESS_KEY_ID=$(AWS_ACCESS_KEY_ID) \
		--env AWS_SECRET_ACCESS_KEY=$(AWS_SECRET_ACCESS_KEY) \
		--env AWS_SESSION_TOKEN=$(AWS_SESSION_TOKEN) \
		app sh -c "cd /app && npm run deploy:prod"

destroy:	## Destroy
	@docker-compose exec \
		--env AWS_ACCESS_KEY_ID=$(AWS_ACCESS_KEY_ID) \
		--env AWS_SECRET_ACCESS_KEY=$(AWS_SECRET_ACCESS_KEY) \
		--env AWS_SESSION_TOKEN=$(AWS_SESSION_TOKEN) \
		app sh -c "cd /app && npm run destroy"

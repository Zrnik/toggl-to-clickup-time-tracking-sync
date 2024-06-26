build:
	docker build . -t toggl_to_clickup_image -f Dockerfile

composer-update: build
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image composer update
	sudo chmod 777 -R vendor

composer-install: build
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image composer install
	sudo chmod 777 -R vendor

phpstan:
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image php vendor/bin/phpstan analyse app -l 8

upload:
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image php bin/console tool:sync

ecs:
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image php vendor/bin/ecs

ecs-fix:
	docker run -w /app -v $(shell pwd):/app toggl_to_clickup_image php vendor/bin/ecs --fix
	sudo chmod -R 777 ./*

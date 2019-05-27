install:
	composer install --ignore-platform-reqs

run-server-with-rebuild:
	CURRENT_UID=ec:$(id -g) CURRENT_U=$(id -u) CURRENT_G=$(id -g)   docker-compose up --build



run-server:
	CURRENT_UID=$(id -u):$(id -g) CURRENT_U=$(id -u) CURRENT_G=$(id -g)  docker-compose up

stop-server:
	docker rm -f $(docker ps -a -q)


terminal:
	CONTAINER_ID=$(shell docker inspect --format="{{.Id}}" orm_php ) && docker exec -it $$CONTAINER_ID bash



tests:

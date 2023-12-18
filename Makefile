
all: build kill run

build:
	docker build -t webcam .

kill:
	docker kill webcam || echo ""
	docker rm webcam || echo ""

run:
	docker run -d --name webcam --restart always -v /opt/webcam:/opt/webcam \
		-e "MYSQL_CNF=$(MYSQL_CNF)" \
		-e USERS="wcupload|hc01access|/staging|1001" \
		webcam

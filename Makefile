
all: build kill run

MYSQL_CNF = /home/tomh/microservices/webcam/secrets/webcam_citysupply.cnf

build:
	docker build -t webcam .

kill:
	docker kill webcam || echo ""
	docker rm webcam || echo ""

run:
	docker run -d --name webcam --restart always -v $(MYSQL_CNF):$(MYSQL_CNF) -e "MYSQL_CNF=$(MYSQL_CNF)" webcam

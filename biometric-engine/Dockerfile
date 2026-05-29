FROM maven:3.9-eclipse-temurin-17 AS java-builder

WORKDIR /java

COPY java/pom.xml .
RUN mvn -q dependency:copy-dependencies

COPY java/src ./src
RUN mvn -q compile

FROM python:3.12-slim

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends openjdk-21-jre-headless \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py .
COPY --from=java-builder /java/target/classes /app/java/classes
COPY --from=java-builder /java/target/dependency /app/java/dependency

EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]

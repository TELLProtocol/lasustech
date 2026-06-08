FROM python:3.11-slim

ENV PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt .
RUN apt-get update && apt-get install -y --no-install-recommends gcc libffi-dev && rm -rf /var/lib/apt/lists/*
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

ENV PORT=8080

# Tell gunicorn where app.py actually lives
CMD exec gunicorn --bind :$PORT --workers 1 --threads 8 --timeout 0 config.app:app

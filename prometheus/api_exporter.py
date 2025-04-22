#!/usr/bin/env python3

import os
import time
import re
import argparse
from prometheus_client import start_http_server, Gauge
import requests

# Словарь для хранения метрик
api_metrics = {}

# Максимально допустимая разница во времени
MAX_TIME_DIFF = 10  # Укажите интервал в секундах

def seconds_to_dhms(seconds):
    """Конвертирует секунды в формат XX days HH:MM:SS"""
    seconds = int(seconds)
    days = seconds // 86400
    hours = (seconds % 86400) // 3600
    minutes = (seconds % 3600) // 60
    seconds = seconds % 60

    if days > 0:
        return f"{days} days {hours:02d}:{minutes:02d}:{seconds:02d}"
    else:
        return f"{hours:02d}:{minutes:02d}:{seconds:02d}"

def sanitize_metric_name(name):
    """Преобразует описание метрики в валидное имя"""
    name = name.lower()
    name = re.sub(r'[^\w]', '_', name)
    name = re.sub(r'_+', '_', name)
    name = name.strip('_')
    return name

def fetch_data(api_host, bearer_token):
    global api_metrics
    try:
        response = requests.get(
            f"{api_host}/stream_json.php",
            headers={"Authorization": f"Bearer {bearer_token}"}
        )
        response.raise_for_status()

        data = response.json()

        if "error" in data:
            print(f"API returned an error: {data['error']}")
            return

        current_time_ms = int(time.time() * 1000)

        for item in data:
            metric_name = sanitize_metric_name(item["description"])
            metric_time = int(item["time"])
            time_diff = (current_time_ms - metric_time) / 1000

            if metric_name not in api_metrics:
                print(f"Metric {metric_name} added")
                api_metrics[metric_name] = Gauge(
                    f"api_{metric_name}",
                    f"Metric for {item['description']}",
                    ["description", "unit"]
                )

            if time_diff > MAX_TIME_DIFF:
                time_str = seconds_to_dhms(time_diff)
                print(f"Skipping outdated metric: {metric_name} (Last updated {time_str} ago)")
                api_metrics[metric_name].labels(description=item["description"], unit=item["unit"]).set(float('nan'))
                continue

            api_metrics[metric_name].labels(description=item["description"], unit=item["unit"]).set(float(item["value"]))

    except requests.exceptions.RequestException as e:
        print(f"Error fetching data from API: {e}")
    except ValueError as e:
        print(f"Error decoding JSON response: {e}")
    except Exception as e:
        print(f"Unexpected error: {e}")

def parse_args():
    parser = argparse.ArgumentParser(description='RedBox Telemetry API exporter for Prometheus')
    parser.add_argument('--host', required=True, help='API host URL')
    parser.add_argument('--token', required=True, help='User token for authentication')
    parser.add_argument('--port', type=int, default=7979, help='Port to expose metrics on (default: 7979)')
    parser.add_argument('--interval', type=int, default=15, help='Polling interval in seconds (default: 15)')
    return parser.parse_args()

if __name__ == "__main__":
    args = parse_args()
    start_http_server(args.port)
    print(f"RedBox Telemetry API exporter for Prometheus is running on port {args.port} and fetching data from {args.host}...")

    while True:
        fetch_data(args.host, args.token)
        time.sleep(args.interval)

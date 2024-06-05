FROM mariadb:lts
WORKDIR "/data"

RUN apt-get update && apt-get -y install mariadb-plugin-rocksdb && rm -rf /var/cache/apt/lists/*

# Telephantast

PHP Messaging System

## Demo

```shell
# Setup
composer create-project --stability=dev telephantast/demo telephantast
cd telephantast
docker-compose up --remove-orphans --detach --build

# Send ping and start wait for pong
docker-compose run -it php php ping_sender.php

# In separate process handle ping and reply with pong
docker-compose run -it php php ping_receiver.php
```

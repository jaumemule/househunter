# Installation instructions
- Modify services.yaml configuration
- Then `docker-compose build` will make all installations possible
- To put it up and running: `docker-compose up -d`
- Enjoy!

# Extras

- The app will send health check controls every 4h to inform that it is still alive.
- If there are permission issues in a server, do not execute the scrap command and instead get into the container to execute composer install via `sudo su` and `docker exec -u 0 -it mycontainer sh` to import root permissions
# Restart

- There is not really any need to restart on every deployment. It would be detrimental to data collection process to do so unless:
    - The agent has changed.
    - The agent has received data it no longer understands, e.g, a payload version change.
    - Environment variables / configuration variables that the agent requires has changed. Currently this is only the `NIGHTWATCH_TOKEN` which is unlikely to change enough to make restarting on every deploy worth it.

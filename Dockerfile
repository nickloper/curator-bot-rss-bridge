FROM rssbridge/rss-bridge:latest

# Copy custom bridges into the container
COPY bridges/* /app/bridges/

# Expose port 80 for the web server
EXPOSE 80

# The base image already has everything configured
# Just run apache in the foreground
CMD ["apache2-foreground"]

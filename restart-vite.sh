#!/bin/bash

# Kill any running Vite processes
echo "Stopping any running Vite processes..."
pkill -f vite || true

# Clear Vite cache
echo "Clearing Vite cache..."
npm run clean

# Start Vite dev server
echo "Starting Vite dev server..."
npm run dev 
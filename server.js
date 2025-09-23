// server.js
import express from "express";
import dotenv from "dotenv";

import { authHandler } from "./src/Http/RequestHandlers/AuthHandler.js";
import { authCallbackHandler } from "./src/Http/RequestHandlers/AuthCallbackHandler.js";
import { FitbitClient } from "./src/Services/FitbitClient.js";

dotenv.config();

const app = express();

// Load Fitbit client configuration from .env
const fitbitClient = new FitbitClient({
  clientId: process.env.FITBIT_CLIENT_ID,
  clientSecret: process.env.FITBIT_CLIENT_SECRET,
  redirectUri: process.env.FITBIT_REDIRECT_URI,
});

// Attach routes
app.get("/auth", (req, res) => authHandler(req, res, fitbitClient));
app.get("/callback", (req, res) => authCallbackHandler(req, res, fitbitClient));

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Server running at http://localhost:${PORT}`);
});

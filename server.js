// server.js
import express from "express";
import dotenv from "dotenv";
import dayjs from "dayjs";

import { authHandler } from "./src/Http/RequestHandlers/AuthRequestHandler.js";
import { authCallbackHandler } from "./src/Http/RequestHandlers/AuthCallbackRequestHandler.js";
import { FitbitClient } from "./src/Services/FitbitClient.js";
import { MySQL } from "./src/Services/MySQL.js";

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

app.get('/weight-logs', async (req, res) => {
  try {
    let { start, end } = req.query;

    // Default values
    let startDate = start || '1900-01-01';
    let endDate = end || '2025-09-22';

    // Validate and normalize dates
    const yesterday = dayjs().format('yyyy-MM-dd');

    if (endDate !== 'yesterday') {
      if (!dayjs(endDate, 'yyyy-MM-dd', true).isValid()) {
        return res.status(400).json({
          status: 'error',
          message: `Invalid end date format: ${endDate}. Use yyyy-MM-dd or "yesterday".`,
        });
      }
      if (dayjs(endDate).isAfter(yesterday)) {
        endDate = 'yesterday';
      }
    }

    if (!dayjs(startDate, 'yyyy-MM-dd', true).isValid()) {
      return res.status(400).json({
        status: 'error',
        message: `Invalid start date format: ${startDate}. Use yyyy-MM-dd.`,
      });
    }

    const logs = await fitbitClient.getWeightLogs(startDate, endDate);

    res.json({
      status: 'success',
      count: logs.weight?.length || 0,
      data: logs.weight || [],
    });
  } catch (err) {
    console.error(err.response?.data || err.message);

    res.status(500).json({
      status: 'error',
      message: 'Failed to fetch weight logs',
      details: err.response?.data || err.message,
    });
  }
});

app.post('/sync', async (request, response) => {

  const database = new MySQL()
  await database.connect()

  const pool = database.getPool()

  const sql = `
    SELECT MAX(date) AS latest_date 
    FROM weight_logs
  `

  const [rows] = pool.execute(sql)
  const latestDate = rows[0]?.latest_date || null

  let startDate = latestDate ? dayjs(latestDate).add(1, 'day').format('YYYY-MM-DD') : '1900-01-01'
  let endDate = dayjs().subtract(1, 'day').format('YYYY-MM-DD')

  if (dayjs(startDate).isAfter(endDate)) {
    return response.json({
      message: 'No new data to sync',
    })
  } else {

    const logs = await fitbitClient.getWeightLogs(startDate, endDate)

    if (logs.length === 0) {
      return response.json({
        message: 'No new data to sync',
      })
    } else {
      const insertSql = `
        INSERT INTO weight_logs (date, weight, bmi)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          weight = VALUES(weight),
          bmi = VALUES(bmi)
      `

      const insertPromises = logs.map(log => {
        return pool.execute(insertSql, [log.date, log.weight, log.bmi])
      })  
      await Promise.all(insertPromises)
      return response.json({
        message: `Successfully synced ${logs.length} records from ${startDate} to ${endDate}`,
        syncedRecords: logs.length,
        startDate,
        endDate
      })
    }

  }



  fitbitClient.getWeightLogs()
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Server running at http://localhost:${PORT}`);
});

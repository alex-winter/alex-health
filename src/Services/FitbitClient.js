import axios from "axios";
import { readFile, writeFile } from "fs/promises";
import path from "path";
import dayjs from "dayjs";

const tokenFilePath = path.resolve("./tokens.json");

export class FitbitClient {
  constructor({ clientId, clientSecret, redirectUri }) {
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.redirectUri = redirectUri;
    this.baseUrl = "https://api.fitbit.com";
  }

  getAuthUrl() {
    const scope = "activity heartrate sleep weight";
    return `https://www.fitbit.com/oauth2/authorize` +
      `?response_type=code` +
      `&client_id=${this.clientId}` +
      `&redirect_uri=${encodeURIComponent(this.redirectUri)}` +
      `&scope=${encodeURIComponent(scope)}`;
  }

  async getToken(code) {
    const tokenUrl = `${this.baseUrl}/oauth2/token`;
    const creds = Buffer.from(`${this.clientId}:${this.clientSecret}`).toString("base64");

    const response = await axios.post(
      tokenUrl,
      new URLSearchParams({
        code,
        grant_type: "authorization_code",
        redirect_uri: this.redirectUri,
      }),
      {
        headers: {
          Authorization: `Basic ${creds}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
      }
    );

    const tokens = response.data;
    
    await this.saveTokens(tokens);

    return tokens;
  }

  async refreshToken(refreshToken) {
    const tokenUrl = `${this.baseUrl}/oauth2/token`;
    const creds = Buffer.from(`${this.clientId}:${this.clientSecret}`).toString("base64");

    const response = await axios.post(
      tokenUrl,
      new URLSearchParams({
        refresh_token: refreshToken,
        grant_type: "refresh_token",
      }),
      {
        headers: {
          Authorization: `Basic ${creds}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
      }
    );

    const tokens = response.data;
    await this.saveTokens(tokens);
    return tokens;
  }

  async saveTokens(tokens) {
    const data = {
      ...tokens,
      obtained_at: Date.now(),
    };

    await writeFile(tokenFilePath, JSON.stringify(data, null, 2), "utf8");
  }

  async loadTokens() {
    try {
      const data = await readFile(tokenFilePath, "utf8");
      return JSON.parse(data);
    } catch {
      return null;
    }
  }

  async getAccessToken() {
    let tokens = await this.loadTokens();
    if (!tokens) throw new Error("No tokens found, please authenticate first.");

    const expiresInMs = tokens.expires_in * 1000;
    const expiryTime = tokens.obtained_at + expiresInMs;

    if (Date.now() >= expiryTime) {
      tokens = await this.refreshToken(tokens.refresh_token);
    }

    return tokens.access_token;
  }

  async getWeightLogForDay(date) {
    const accessToken = await this.getAccessToken();
    const url = `${this.baseUrl}/1/user/-/body/log/weight/date/${date}.json`;

    const response = await axios.get(url, {
      headers: { Authorization: `Bearer ${accessToken}` },
    });

    return response.data.weight || [];
  }

  async getWeightLogs(startDate = '1900-01-01', endDate) {
    const today = dayjs().format("YYYY-MM-DD");
    let finalEndDate = endDate ? (dayjs(endDate).isAfter(today) ? today : endDate) : today;

    const logs = [];
    let currentDate = dayjs(startDate);

    while (currentDate.isBefore(dayjs(finalEndDate).add(1, "day"))) {
      const dateStr = currentDate.format("YYYY-MM-DD");
      try {
        const dailyLogs = await this.getWeightLogForDay(dateStr);
        logs.push(...dailyLogs);
      } catch (err) {
        console.error(`Failed to fetch weight for ${dateStr}:`, err.response?.data || err.message);
      }
      currentDate = currentDate.add(1, "day");
    }

    return logs.map(log => ({
      date: log.date,
      weight: log.weight,
      bmi: log.bmi,
    }));
  }
}

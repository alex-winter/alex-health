import axios from "axios";

export class FitbitClient {
  constructor({ clientId, clientSecret, redirectUri }) {
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.redirectUri = redirectUri;
    this.baseUrl = "https://api.fitbit.com";
  }

  getAuthUrl() {
    const scope = "activity heartrate sleep"; // adjust scopes as needed
    return `https://www.fitbit.com/oauth2/authorize?response_type=code&client_id=${this.clientId}&redirect_uri=${encodeURIComponent(
      this.redirectUri
    )}&scope=${encodeURIComponent(scope)}`;
  }

  async getToken(code) {
    const tokenUrl = `${this.baseUrl}/oauth2/token`;
    const creds = Buffer.from(
      `${this.clientId}:${this.clientSecret}`
    ).toString("base64");

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

    return response.data;
  }
}

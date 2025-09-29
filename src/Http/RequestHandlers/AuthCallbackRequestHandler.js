import { writeFile } from "fs/promises";
import path from "path";

const tokenFilePath = path.resolve("./tokens.json");

export async function authCallbackHandler(req, res, fitbitClient) {
  const { code } = req.query;

  if (!code) {
    return res.status(400).send("Missing authorization code");
  }

  try {
    const token = await fitbitClient.getToken(code);

    // Save token to file
    await writeFile(tokenFilePath, JSON.stringify(token, null, 2), "utf8");

    res.json({
      message: "Token saved successfully",
      token,
    });
  } catch (err) {
    console.error(err.response?.data || err.message);
    res.status(500).send("Error exchanging code for token");
  }
}
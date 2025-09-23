export async function authCallbackHandler(req, res, fitbitClient) {
  const { code } = req.query;

  if (!code) {
    return res.status(400).send("Missing authorization code");
  }

  try {
    const tokenData = await fitbitClient.getToken(code);
    res.json(tokenData);
  } catch (err) {
    console.error(err.response?.data || err.message);
    res.status(500).send("Error exchanging code for token");
  }
}
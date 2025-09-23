export function authHandler(req, res, fitbitClient) {
  const url = fitbitClient.getAuthUrl();
  res.redirect(url);
}
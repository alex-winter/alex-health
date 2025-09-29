export class MySQL {
    pool

    async connect() {
        let attempts = 0

        while (attempts < 5) {
            try {
                this.pool = await mysql.createPool({
                    host: process.env.DB_HOST,  
                    user: process.env.DB_USER,
                    password: process.env.DB_PASSWORD,
                    database: process.env.DB_NAME,
                    waitForConnections: true,
                })
                
                const connection = await this.pool.getConnection()
                
                connection.release()

                return
            }

            catch (error) {
                attempts++
                console.error(`MySQL connection attempt ${attempts} failed:`, error)
                if (attempts >= 5) {
                    throw new Error("Failed to connect to MySQL database after 5 attempts")
                }

                await new Promise(resolve => setTimeout(resolve, 2000))
            }
        }
    }

    getPool() {
        if (!this.pool) {
            throw new Error("Database not connected. Call connect() first.")
        }   
        return this.pool
    }
}
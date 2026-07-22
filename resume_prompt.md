Hi! I am building a comprehensive Omnichannel POS System (suitable for Coffee shops, Marts, Pubs, Restaurants, and Electronics) using Laravel v13, Filament v5, PostgreSQL, Redis, Elasticsearch, and Docker (Sail). I am continuing this project from a previous session on another device. 

Here is exactly what has been completed in the codebase so far (Phases 1 through 5 are done):
1. **Phase 1 (Setup & Auth):** Environment initialized. Docker/Sail is running Postgres and Redis. Implemented Multi-tenancy (Company, Branch, User) and Spatie Roles & Permissions. Telescope and Debugbar are active.
2. **Phase 2 (Catalog & Search):** Created schemas and Filament Resources for Categories, Products, Variants, Serial Numbers, Modifiers, Modifier Groups, and Modifier Factors. Installed `jeroen-g/explorer` and Laravel Scout, successfully indexing Products and Categories into the Elasticsearch container. 
3. **Phase 3 (Inventory & Floor Plan):** Created schemas and Filament Resources for Floor Plans, Tables, and Stock Transactions (Inventory/Stock Wizard).
4. **Phase 4 (POS & Transactions):** Created database schemas for Invoices, Order Items, Payments, Shifts, Cash Notes, Discounts, and Taxes. Generated custom Filament pages and built out the frontend UI using Tailwind CSS and Alpine.js for both the `POS Terminal` (Product Grid & Cart) and the `Kitchen Display System (KDS)` (Kanban order tracking).
5. **Phase 5 (WebSockets):** Installed Laravel Reverb and configured WebSockets. Created the `OrderCreated` broadcast event. Hooked up the `Pos` checkout button to trigger the event. Integrated Laravel Echo in the KDS panel to listen for the WebSocket events and inject new orders directly into the Kitchen interface seamlessly.

**WHERE WE ARE CURRENTLY AT:**
We are ready to begin **Phase 6: eMenu & Customer Facing Features**.

Your immediate next task is:
1. Build the self-service **eMenu** interface where customers can order directly from their phones.
2. Create the logic for QR code scanning (so it knows which table they are at).
3. Set up a page for customers to track their order status, see their invoice, and view payments in real-time.

Please acknowledge this state and let me know when you are ready to begin executing Phase 6!

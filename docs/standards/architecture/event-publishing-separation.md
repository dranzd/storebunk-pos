<!-- hash: 437f2b3462598ad34f8346e34915bb989dac6b040bda7c98479410d47ff3ce70 -->
# event-publishing-separation

Category: architecture
Status: stable
Source: storebunk-pos

---

Event publishing is NOT provided by the core library. Consumers must implement their own event publishing strategy (Laravel Events, Symfony EventDispatcher, RabbitMQ, etc.). Repository implementations are responsible for dispatching events after persisting aggregates.

Event publishing is **NOT provided** by this library. Consumers implement their own event publishing strategy:

- Laravel Events
- Symfony EventDispatcher
- RabbitMQ / Kafka
- Custom event bus

Repository implementations are responsible for dispatching events after persisting aggregates.

---

## Source File
docs/technical_design.md

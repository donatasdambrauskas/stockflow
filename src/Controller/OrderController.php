<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\WarehouseRepository;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly WarehouseRepository $warehouseRepository,
    ) {
    }

    #[Route('/orders', name: 'api_orders_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !isset($payload['items']) || !\is_array($payload['items'])) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Expected JSON body with an items array.');
        }

        try {
            $order = $this->orderService->createOrder($payload['items']);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, $exception->getMessage(), $exception);
        }

        return $this->json($this->presentOrder($order), Response::HTTP_CREATED);
    }

    #[Route('/orders/{id}', name: 'api_orders_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(Response::HTTP_NOT_FOUND, $exception->getMessage(), $exception);
        }

        return $this->json($this->presentOrder($order));
    }

    #[Route('/orders/{id}/ship', name: 'api_orders_ship', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function ship(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->shipOrder($id);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(Response::HTTP_NOT_FOUND, $exception->getMessage(), $exception);
        } catch (\DomainException $exception) {
            throw new HttpException(Response::HTTP_CONFLICT, $exception->getMessage(), $exception);
        }

        return $this->json($this->presentOrder($order));
    }

    #[Route('/orders/{id}/cancel', name: 'api_orders_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($id);
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(Response::HTTP_NOT_FOUND, $exception->getMessage(), $exception);
        } catch (\DomainException $exception) {
            throw new HttpException(Response::HTTP_CONFLICT, $exception->getMessage(), $exception);
        }

        return $this->json($this->presentOrder($order));
    }

    #[Route('/warehouses', name: 'api_warehouses_list', methods: ['GET'])]
    public function warehouses(): JsonResponse
    {
        $warehouses = $this->warehouseRepository->findAll();

        $data = array_map(static function ($warehouse): array {
            $stock = [];

            foreach ($warehouse->getStocks() as $warehouseStock) {
                $stock[] = [
                    'sku' => $warehouseStock->getProduct()->getSku(),
                    'quantity' => $warehouseStock->getQuantity(),
                    'reserved_quantity' => $warehouseStock->getReservedQuantity(),
                    'available_quantity' => $warehouseStock->getAvailableQuantity(),
                ];
            }

            return [
                'id' => $warehouse->getId(),
                'code' => $warehouse->getCode(),
                'name' => $warehouse->getName(),
                'stock' => $stock,
            ];
        }, $warehouses);

        return $this->json(['warehouses' => $data]);
    }

    /** @return array<string, mixed> */
    private function presentOrder(Order $order): array
    {
        $items = [];
        $missingItems = [];
        $reservations = [];

        foreach ($order->getItems() as $item) {
            $items[] = [
                'sku' => $item->getProduct()->getSku(),
                'quantity_requested' => $item->getQuantityRequested(),
                'quantity_reserved' => $item->getQuantityReserved(),
            ];

            if ($item->getMissingQuantity() > 0) {
                $missingItems[] = [
                    'sku' => $item->getProduct()->getSku(),
                    'quantity_missing' => $item->getMissingQuantity(),
                ];
            }
        }

        foreach ($order->getReservations() as $reservation) {
            $reservations[] = [
                'warehouse_code' => $reservation->getWarehouse()->getCode(),
                'sku' => $reservation->getProduct()->getSku(),
                'quantity' => $reservation->getQuantity(),
            ];
        }

        return [
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'created_at' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'items' => $items,
            'reservations' => $reservations,
            'missing_items' => $missingItems,
        ];
    }
}

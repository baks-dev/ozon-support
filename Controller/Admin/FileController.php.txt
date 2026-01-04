<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Support\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Ozon\Support\Api\Get\ChatFile\GetOzonFileChatRequest;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DomainException;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_SUPPORT')]
final class FileController extends AbstractController
{
    /**
     * При изменении ссылки изменить ссылку в OzonMessageChatDTO
     * @see OzonMessageChatDTO:145
     */
    #[Route(
        path: '/admin/ozon-support/files/{ticket}/{message}/{file}/info',
        name: 'admin.ozon.support.files',
        methods: ['GET'],
    )]
    public function index(
        GetOzonFileChatRequest $getOzonLinkRequest,
        string $ticket,
        string $message,
        string $file,
    ): Response
    {
        $fileInfo = pathinfo($file);

        $UserProfileUid = $this->getProfileUid();

        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Не указан профиль пользователя для просмотра файла');
        }

        $content = $getOzonLinkRequest
            ->forTokenIdentifier($UserProfileUid)
            ->ticket($ticket)
            ->message($message)
            ->file($file)
            ->get();

        if(false === $content)
        {
            throw new DomainException('Просмотр данного файла не доступен.');
        }

        $response = new StreamedResponse(
            function() use ($content) {
                echo $content;
            }, Response::HTTP_OK,
            [
                'Cache-Control', 'private',
                'Content-Type' => $fileInfo['extension'],
                'Content-Length' => (string) strlen($content),
            ]
        );

        $response->send();

        return $response;
    }
}

<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\Body;

use App\Application\DataTransformer\Apps\Body\BodyTagMembershipCardDataTransformer;
use App\Application\DataTransformer\BodyElementDataTransformerHandler;
use Ec\Editorial\Domain\Model\Body\BodyElement;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\BodyTagPictureMembership;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Body\MembershipCardButtons;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Jose Guillermo Moreu Peso <jgmoreu@ext.elconfidencial.com>
 */
class BodyTagMembershipCardDataTransformerTest extends TestCase
{

    /** @var BodyElementDataTransformerHandler|MockObject */
    private BodyElementDataTransformerHandler $handler;
    private BodyTagMembershipCardDataTransformer $dataTransformer;

    protected function setUp(): void
    {
        $this->handler = $this->createMock(BodyElementDataTransformerHandler::class);
        $this->dataTransformer = new BodyTagMembershipCardDataTransformer($this->handler);
    }
    /**
     * @test
     */
    public function canTransformShouldReturnBodyTagHtmlString(): void
    {
        static::assertSame(BodyTagMembershipCard::class, $this->dataTransformer->canTransform());
    }
    /**
     * @test
     */
    public function writeShouldReturnExceptionWhenBodyElementIsNotBodyTagMembershipCard(): void
    {
        $bodyElementMock = $this->createMock(BodyElement::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BodyElement should be instance of '.BodyTagMembershipCard::class);

        $this->dataTransformer->write($bodyElementMock)->read();
    }

    /**
     * @test
     */
    public function readShouldReturnExpectedArray(): void
    {
        $buttonMock = $this->createMock(MembershipCardButton::class);
        $buttonMock->expects(static::exactly(2))
            ->method('url')
            ->willReturn('url 1');
        $buttonMock->expects(static::exactly(2))
            ->method('urlMembership')
            ->willReturn('urlMembership 1');
        $buttonMock->expects(static::once())
            ->method('cta')
            ->willReturn('text 1');

        $buttonMock2 = $this->createMock(MembershipCardButton::class);
        $buttonMock2->expects(static::exactly(2))
            ->method('url')
            ->willReturn('url 2');
        $buttonMock2->expects(static::exactly(2))
            ->method('urlMembership')
            ->willReturn('urlMembership 2');
        $buttonMock2->expects(static::once())
            ->method('cta')
            ->willReturn('text 2');

        $buttonCollectionMock = $this->createMock(MembershipCardButtons::class);
        $buttonCollectionMock->expects(static::once())
            ->method('buttons')
            ->willReturn([$buttonMock, $buttonMock2]);

        $type = 'bodytagmembershipcard';
        $title = 'title';
        $titleBanner = 'titleBanner';
        $classBanner = 'classBanner';
        $expected = [
            'type' => $type,
            'title' => $title,
            'buttons' => [
                [
                    'url' => 'url 1',
                    'urlMembership' => 'urlMembership 1',
                    'text' => 'text 1',
                ],
                [
                    'url' => 'url 2',
                    'urlMembership' => 'urlMembership 2',
                    'text' => 'text 2',
                ],
            ],
            'titleBanner' => $titleBanner,
            'classBanner' => $classBanner,
            'picture' => [],
        ];

        $bodyElementMock = $this->createMock(BodyTagMembershipCard::class);

        $bodyElementMock->expects(static::once())
            ->method('type')
            ->willReturn($type);
        $bodyElementMock->expects(static::once())
            ->method('title')
            ->willReturn($title);
        $bodyElementMock->expects(static::once())
            ->method('buttons')
            ->willReturn($buttonCollectionMock);

        $bodyElementMock->expects(static::once())
            ->method('titleBanner')
            ->willReturn($titleBanner);
        $bodyElementMock->expects(static::once())
            ->method('classBanner')
            ->willReturn($classBanner);

        $pictureMock = $this->createMock(BodyTagPictureMembership::class);

        $bodyElementMock->expects(static::once())
            ->method('bodyTagPictureMembership')
            ->willReturn($pictureMock);

        $this->handler->expects(static::once())
            ->method('execute')
            ->with($pictureMock, []);

        $result = $this->dataTransformer->write($bodyElementMock)->read();

        static::assertSame($expected, $result);
    }

}
